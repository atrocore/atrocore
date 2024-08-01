<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\FileStorage;

use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Utils\Thumbnail;
use Atro\Core\Utils\Xattr;
use Atro\Entities\File;
use Atro\Entities\Folder;
use Atro\Entities\Storage;
use Atro\EntryPoints\Image;
use Doctrine\DBAL\Connection;
use Espo\Core\FilePathBuilder;
use Atro\Core\Utils\FileManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Psr\Http\Message\StreamInterface;

class LocalStorage implements FileStorageInterface, LocalFileStorageInterface
{
    public const CHUNKS_DIR = '.chunks';
    public const TMP_DIR = '.tmp';
    public const TRASH_DIR = '.trash';

    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public static function parseInputFileContent(string $fileContent): string
    {
        $arr = explode(',', $fileContent);
        $contents = '';
        if (count($arr) > 1) {
            $contents = $arr[1];
        }

        return base64_decode($contents);
    }

    public function scan(Storage $storage): void
    {
        $xattr = new Xattr();
        if (!$xattr->hasServerExtensions()) {
            throw new \Error("Xattr extension is not installed and the attr command is not available. See documentation for details.");
        }

        $otherStorages = $this->getEntityManager()->getRepository('Storage')
            ->where([
                'id!='     => $storage->get('id'),
                'type'     => 'local',
                'isActive' => true
            ])
            ->find();

        $this->scanFolders($storage, $otherStorages, $xattr);
        $this->scanFiles($storage, $otherStorages, $xattr);
    }

    public function createFile(File $file): bool
    {
        $result = false;

        $input = $file->_input ?? new \stdClass();

        if (!$file->getStorage()->get('syncFolders')) {
            $file->set('path', $this->getPathBuilder()->createPath($file->getStorage()->get('path') . DIRECTORY_SEPARATOR));
        }

        $fileName = $this->getLocalPath($file);

        // create folders for new file
        $this->getFileManager()->mkdir($this->getFileManager()->getFileDir($fileName), 0777, true);

        /**
         * Create via contents
         */
        if (property_exists($input, 'fileContents')) {
            $result = file_put_contents($fileName, self::parseInputFileContent($input->fileContents));
        }

        /**
         * Create via chunks
         */
        if (!$result && property_exists($input, 'allChunks')) {
            $chunkDirPath = $this->getChunksDir($file->getStorage()) . DIRECTORY_SEPARATOR . $input->fileUniqueHash;

            // create file via chunks
            $f = fopen($fileName, 'a+');
            foreach ($input->allChunks as $chunk) {
                fwrite($f, file_get_contents($chunkDirPath . DIRECTORY_SEPARATOR . $chunk));
            }
            fclose($f);

            $result = true;
        }

        /**
         * Create via remote URL
         */
        if (!$result && property_exists($input, 'remoteUrl')) {
            // if url use file protocol
            if (str_starts_with($input->remoteUrl, 'file://')) {
                $localFileName = str_replace('file://', '', $input->remoteUrl);
                if (!file_exists($localFileName)) {
                    throw new Error("File $localFileName does not exist");
                }
                $result = copy($localFileName, $fileName);
            } else {
                // headers should be passed as key-value structure
                $headers = $input->urlHeaders ?? null;
                if (is_object($headers)) {
                    $headers = json_decode(json_encode($headers), true);
                } else {
                    if (is_string($headers)) {
                        $headers = @json_decode($headers, true);
                    }
                }

                // load file from url
                set_time_limit(0);
                $fp = fopen($fileName, 'w+');
                if ($fp === false) {
                    throw new Error(sprintf("Can't write any data to the file %s", $file->get('name')));
                }
                $ch = curl_init($input->remoteUrl);
                curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                if (is_array($headers) && !empty($headers)) {
                    $requestHeaders = [];
                    foreach ($headers as $header => $value) {
                        $requestHeaders[] = "$header: $value";
                    }
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
                }

                curl_exec($ch);
                $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                fclose($fp);

                if (!in_array($responseCode, [200, 201])) {
                    if (file_exists($fileName)) {
                        @unlink($fileName);
                    }
                    throw new Error(sprintf("Download for '%s' failed.", $input->remoteUrl));
                }

                $result = true;
            }
        }

        /**
         * Create via local file
         */
        if (!$result && property_exists($input, 'localFileName')) {
            $result = file_exists($input->localFileName) && rename($input->localFileName, $fileName);
        }

        if ($result) {
            $file->set('fileMtime', gmdate("Y-m-d H:i:s", filemtime($fileName)));
            $file->set('mimeType', mime_content_type($fileName));
            $file->set('fileSize', filesize($fileName));
            $file->set('hash', $this->getFileManager()->md5File($fileName));

            $xattr = new Xattr();
            $xattr->set($fileName, 'atroId', $file->id);
        }

        return $result;
    }

    public function createFolder(Folder $folder): bool
    {
        if (!$folder->getStorage()->get('syncFolders')) {
            return true;
        }

        $folderName = self::buildFullPath($folder->getStorage(), self::buildFolderPath($folder));

        // create folder
        $this->getFileManager()->mkdir($folderName, 0777, true);

        $xattr = new Xattr();
        $xattr->set($folderName, 'atroId', $folder->id);

        return true;
    }

    public function createChunk(\stdClass $input, Storage $storage): array
    {
        $path = $this->getChunksDir($storage) . DIRECTORY_SEPARATOR . $input->fileUniqueHash;

        $this->getFileManager()->putContents($path . DIRECTORY_SEPARATOR . $input->start, self::parseInputFileContent($input->piece));

        $chunkFiles = $this->getFileManager()->scanDir($path);
        sort($chunkFiles);

        $chunks = [];
        foreach ($chunkFiles as $chunkFile) {
            $chunks[] = $chunkFile;
        }

        return $chunks;
    }

    public function deleteCache(Storage $storage): void
    {
        $this->getFileManager()->removeAllInDir($this->getChunksDir($storage));
    }

    public function renameFile(File $file): bool
    {
        $from = $this->getLocalPath($file, true);
        $to = $this->getLocalPath($file);

        if (file_exists($from)) {
            return $this->getFileManager()->move($from, $to);
        }

        return false;
    }

    public function moveFile(File $file): bool
    {
        if (!$file->getStorage()->get('syncFolders')) {
            return true;
        }

        return $this->renameFile($file);
    }

    public function renameFolder(Folder $folder): bool
    {
        $storage = $folder->getStorage();

        if (!$storage->get('syncFolders')) {
            return true;
        }

        if ($storage->get('folderId') === $folder->get('id')) {
            return true;
        }

        $folderNameFrom = self::buildFullPath($folder->getStorage(), self::buildFolderPath($folder, true));
        if (!file_exists($folderNameFrom)) {
            return true;
        }

        $folderNameTo = self::buildFullPath($folder->getStorage(), self::buildFolderPath($folder));

        return rename($folderNameFrom, $folderNameTo);
    }

    public function moveFolder(string $entityId, string $wasParentId, string $becameParentId): bool
    {
        /** @var \Atro\Repositories\Folder $folderRepo */
        $folderRepo = $this->getEntityManager()->getRepository('Folder');

        $folder = $folderRepo->get($entityId);

        if (!$folder->getStorage()->get('syncFolders')) {
            return true;
        }

        $parentPathWas = empty($wasParentId) ? '' : self::buildFolderPath($folderRepo->get($wasParentId));
        if (!empty($parentPathWas)) {
            $parentPathWas .= DIRECTORY_SEPARATOR;
        }
        $parentPathBecame = empty($becameParentId) ? '' : self::buildFolderPath($folderRepo->get($becameParentId));
        if (!empty($parentPathBecame)) {
            $parentPathBecame .= DIRECTORY_SEPARATOR;
        }

        $folderNameFrom = self::buildFullPath($folder->getStorage(), $parentPathWas . $folder->get('name'));
        if (!file_exists($folderNameFrom)) {
            return false;
        }

        $folderNameTo = self::buildFullPath($folder->getStorage(), $parentPathBecame . $folder->get('name'));

        return rename($folderNameFrom, $folderNameTo);
    }

    public function reupload(File $file): bool
    {
        return $this->deleteFile($file) && $this->createFile($file);
    }

    public function deleteFile(File $file): bool
    {
        /** @var Thumbnail $thumbnailCreator */
        $thumbnailCreator = $this->container->get(Thumbnail::class);

        // delete thumbnails
        foreach (['small', 'medium', 'large'] as $size) {
            if ($thumbnailCreator->hasThumbnail($file, $size)) {
                @unlink($thumbnailCreator->preparePath($file, $size));
            }
        }

        $path = $this->getLocalPath($file);
        if (file_exists($path)) {
            return $this->getFileManager()->move($path, $this->getFileTrashPath($file));
        }

        return true;
    }

    public function deleteFilePermanently(File $file): bool
    {
        $trashPath = $this->getFileTrashPath($file);
        if (file_exists($trashPath)) {
            $this->getFileManager()->removeFile($trashPath);
        }

        try {
            $path = $this->getLocalPath($file);
            if (file_exists($path)) {
                $this->getFileManager()->removeFile($path);
            }
        } catch (NotFound $e) {
        }

        return true;
    }

    public function restoreFile(File $file): bool
    {
        $trashPath = $this->getFileTrashPath($file);
        if (file_exists($trashPath)) {
            $this->getFileManager()->move($trashPath, $this->getLocalPath($file));
        }

        return true;
    }

    public function getFileTrashPath(File $file): string
    {
        $storagePath = $file->getStorage()->get('path');
        $trashDir = $storagePath . DIRECTORY_SEPARATOR . self::TRASH_DIR;

        $this->getFileManager()->mkdir($trashDir, 0777, true);

        return $trashDir . DIRECTORY_SEPARATOR . $file->get('id');
    }

    public function deleteFolder(Folder $folder): bool
    {
        if (!$folder->getStorage()->get('syncFolders')) {
            return true;
        }

        $folderName = self::buildFullPath($folder->getStorage(), self::buildFolderPath($folder));
        if (!file_exists($folderName)) {
            return true;
        }

        $this->getFileManager()->removeAllInDir($folderName);

        return true;
    }

    public function deleteFolderPermanently(Folder $folder): bool
    {
        return true;
    }

    public function restoreFolder(Folder $folder): bool
    {
        return $this->createFolder($folder);
    }

    public function getContents(File $file): string
    {
        return file_get_contents($this->getLocalPath($file));
    }

    public function getLocalPath(File $file, bool $fetched = false): string
    {
        $method = $fetched ? 'getFetched' : 'get';

        if ($file->getStorage()->get('syncFolders')) {
            $folderId = $file->$method('folderId');
            if (!empty($folderId)) {
                $folder = $this->getEntityManager()->getRepository('Folder')->get($folderId);
                if (empty($folder)) {
                    throw new NotFound("Folder '$folderId' not found.");
                }
            }

            $folderPath = !empty($folder) ? self::buildFolderPath($folder) : '';
            return self::buildFullPath($file->getStorage(), $folderPath) . DIRECTORY_SEPARATOR . $file->$method("name");
        }

        return self::buildFullPath($file->getStorage(), $file->$method('path')) . DIRECTORY_SEPARATOR . $file->$method("name");
    }

    public function getStream(File $file): StreamInterface
    {
        return \GuzzleHttp\Psr7\Utils::streamFor(fopen($this->getLocalPath($file), 'r'));
    }

    public function getUrl(File $file): string
    {
        $url = '?entryPoint=';
        if (in_array($file->get('mimeType'), Image::TYPES)) {
            $url .= 'image';
        } else {
            $url .= 'download';
        }
        $url .= "&id={$file->get('id')}";

        return $this->getConfig()->getSiteUrl() . DIRECTORY_SEPARATOR . $url;
    }

    public function getThumbnail(File $file, string $size): ?string
    {
        /** @var Thumbnail $thumbnailCreator */
        $thumbnailCreator = $this->container->get(Thumbnail::class);

        if ($thumbnailCreator->hasThumbnail($file, $size)) {
            return $thumbnailCreator->preparePath($file, $size);
        }

        return $thumbnailCreator->getPath($file, $size);
    }

    protected static function buildFullPath(Storage $storage, ?string $path): string
    {
        $res = trim($storage->get('path'), DIRECTORY_SEPARATOR);
        if (!empty($path)) {
            $res .= DIRECTORY_SEPARATOR . $path;
        }

        return $res;
    }

    protected static function buildFolderPath(?Folder $folder, bool $fetched = false): string
    {
        $folders = [];
        if (!empty($folder)) {
            $method = $fetched ? 'getFetched' : 'get';
            if ($folder->get('id') !== $folder->getStorage()->get('folderId')) {
                array_unshift($folders, $folder->$method('name'));
            }
            while (true) {
                $parent = $folder->getParent();
                if (empty($parent)) {
                    break;
                }
                $folder = $parent;

                if ($folder->get('id') !== $folder->getStorage()->get('folderId')) {
                    array_unshift($folders, $folder->$method('name'));
                }
            }
        };

        return implode('/', $folders);
    }

    protected function getChunksDir(Storage $storage): string
    {
        return trim($storage->get('path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::CHUNKS_DIR;
    }

    protected function scanFolders(Storage $storage, EntityCollection $otherStorages, Xattr $xattr): void
    {
        if (empty($storage->get('syncFolders'))) {
            return;
        }

        // scan real folders
        $dirs = $this->getStorageDirs(trim($storage->get('path'), DIRECTORY_SEPARATOR));
        foreach ($otherStorages as $otherStorage) {
            if (strlen($otherStorage->get('path')) > strlen($storage->get('path'))) {
                foreach ($dirs as $k => $dir) {
                    if (strpos($dir, $otherStorage->get('path')) === 0) {
                        unset($dirs[$k]);
                    }
                }
            }
        }
        $dirs = array_values($dirs);

        // prepare entity data
        $foldersData = [];
        foreach ($dirs as $dir) {
            $parts = explode(DIRECTORY_SEPARATOR, $dir);
            $dirName = array_pop($parts);

            $id = $xattr->get($dir, 'atroId');

            $entityData = [
                'id'       => $id ?? Util::generateId(),
                'name'     => $dirName,
                '_dirName' => $dir
            ];

            $foldersData[] = $entityData;
        }

        $prefixToRemove = $storage->get('path') . DIRECTORY_SEPARATOR;

        // prepare parents
        foreach ($foldersData as $k => $row) {
            $preparedPath = substr($row['_dirName'], strlen($prefixToRemove));
            if ($preparedPath === $row['name']) {
                $foldersData[$k]['parentId'] = $storage->get('folderId') ?? '';
            } else {
                $pathParts = explode(DIRECTORY_SEPARATOR, $row['_dirName']);
                array_pop($pathParts);
                $checkPath = implode(DIRECTORY_SEPARATOR, $pathParts);

                foreach ($foldersData as $v) {
                    if ($v['_dirName'] === $checkPath) {
                        $foldersData[$k]['parentId'] = $v['id'];
                        break;
                    }
                }
            }
        }

        $folderRepository = $this->getEntityManager()->getRepository('Folder');

        $exists = [];
        foreach ($folderRepository->where(['id' => array_column($foldersData, 'id')])->find() as $folderEntity) {
            $exists[$folderEntity->get('id')] = $folderEntity;
        }

        foreach ($foldersData as $folderData) {
            if (isset($exists[$folderData['id']])) {
                $entity = $exists[$folderData['id']];
            } else {
                $entity = $folderRepository->get();
                $entity->id = $folderData['id'];
                $entity->set('storageId', $storage->get('id'));
            }
            $entity->set('name', $folderData['name']);
            if (!empty($folderData['parentId'])) {
                $entity->set('parentsIds', [$folderData['parentId']]);
            }

            try {
                $this->getEntityManager()->saveEntity($entity, ['scanning' => true]);
                $xattr->set($folderData['_dirName'], 'atroId', $entity->get('id'));
            } catch (NotUnique $e) {
                $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
                    ->where([
                        'parentId'   => $folderData['parentId'],
                        'folderId!=' => null,
                        'name'       => $entity->get('name')
                    ])
                    ->findOne();
                if (!empty($fileFolderLinker)) {
                    $xattr->set($folderData['_dirName'], 'atroId', $fileFolderLinker->get('folderId'));
                }
            }
        }
    }

    protected function scanFiles(Storage $storage, EntityCollection $otherStorages, Xattr $xattr): void
    {
        $limit = 20000;

        /** @var \Atro\Repositories\File $fileRepo */
        $fileRepo = $this->getEntityManager()->getRepository('File');

        /**
         * Mark stored file
         */
        $offset = 0;
        while (true) {
            $files = $fileRepo
                ->where(['storageId' => $storage->get('id')])
                ->limit($offset, $limit)
                ->order('id')
                ->find();

            if (empty($files[0])) {
                break;
            }
            $offset += $limit;

            /** @var File $file */
            foreach ($files as $file) {
                $filePath = $file->getFilePath();
                if (!file_exists($filePath)) {
                    $this->getEntityManager()->removeEntity($file);
                } else {
                    $xattr = new Xattr();
                    $xattr->set($filePath, 'atroId', $file->id);
                }
            }
        }

        $files = $this->getStorageFiles(trim($storage->get('path'), '/'));

        // remove files from other storages
        foreach ($otherStorages as $otherStorage) {
            if (strlen($otherStorage->get('path')) > strlen($storage->get('path'))) {
                foreach ($files as $k => $file) {
                    if (strpos($file, $otherStorage->get('path')) === 0) {
                        unset($files[$k]);
                    }
                }
            }
        }

        $files = array_values($files);

        $ids = [];

        foreach (array_chunk($files, $limit) as $chunk) {
            $toCreate = [];
            $toUpdate = [];
            $toUpdateByFile = [];

            foreach ($chunk as $fileName) {
                $fileInfo = pathinfo($fileName);

                $entityData = [
                    'name'      => $fileInfo['basename'],
                    'fileSize'  => filesize($fileName),
                    'fileMtime' => gmdate("Y-m-d H:i:s", filemtime($fileName)),
                    'hash'      => $this->getFileManager()->md5File($fileName),
                    'mimeType'  => mime_content_type($fileName),
                    'storageId' => $storage->get('id'),
                    '_fileName' => $fileName
                ];

                if (!empty($storage->get('syncFolders'))) {
                    $entityData['folderId'] = $xattr->get($fileInfo['dirname'], 'atroId') ?? $storage->get('folderId');
                } else {
                    $entityData['path'] = ltrim($fileInfo['dirname'], trim($storage->get('path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
                    $entityData['folderId'] = $storage->get('folderId');
                }

                $id = $xattr->get($fileName, 'atroId');
                if (empty($id)) {
                    $toCreate[] = $entityData;
                } else {
                    $toUpdateByFile[$id] = $entityData;
                }
            }

            if (!empty($toUpdateByFile)) {
                $exists = [];
                foreach ($fileRepo->where(['id' => array_keys($toUpdateByFile)])->find() as $v) {
                    $exists[$v->get('id')] = $v;
                }
                foreach ($toUpdateByFile as $k => $v) {
                    if (isset($exists[$k])) {
                        $existEntity = $exists[$k];
                        $skip = true;
                        foreach ($v as $field => $val) {
                            if ($field !== '_fileName' && $existEntity->get($field) !== $val) {
                                $skip = false;
                            }
                        }

                        $ids[] = $k;

                        if (!$skip) {
                            $toUpdate[$k] = $exists[$k];
                            $toUpdate[$k]->set($v);
                            $toUpdate[$k]->_fileName = $v['_fileName'];
                        }
                    } else {
                        $toCreate[] = $v;
                    }
                }
            }

            foreach ($toCreate as $entityData) {
                $entity = $fileRepo->get();
                $entity->set($entityData);
                $this->saveFileViaScan($entity);
                $xattr->set($entityData['_fileName'], 'atroId', $entity->get('id'));
                $ids[] = $entity->get('id');
            }

            foreach ($toUpdate as $entity) {
                try {
                    $this->saveFileViaScan($entity);
                } catch (BadRequest $e) {
                    if (empty($e->getDataItem('skipOnScan'))) {
                        throw $e;
                    }
                }
            }
        }

        $offset = 0;
        while (true) {
            $res = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('file')
                ->where('storage_id=:storageId')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->orderBy('created_at', 'ASC')
                ->setParameter('storageId', $storage->get('id'))
                ->fetchFirstColumn();

            if (empty($res[0])) {
                break;
            }

            $offset += $limit;

            $diff = array_diff($res, $ids);
            if (!empty($diff)) {
                foreach (array_chunk($diff, 20000) as $chunk) {
                    $this->getEntityManager()->getConnection()->createQueryBuilder()
                        ->delete('file')
                        ->where('storage_id = :storageId')
                        ->andWhere('id IN (:ids)')
                        ->setParameter('storageId', $storage->get('id'))
                        ->setParameter('ids', $chunk, Connection::PARAM_STR_ARRAY)
                        ->executeQuery();
                }
            }
        }
    }

    protected function saveFileViaScan(File $file): void
    {
        try {
            $this->getEntityManager()->saveEntity($file, ['scanning' => true]);
        } catch (NotUnique $e) {
            $parts = explode('.', $file->get('name'));
            $ext = array_pop($parts);
            $from = $this->getLocalPath($file);
            $file->set('name', implode('.', $parts) . '_.' . $ext);
            rename($from, $this->getLocalPath($file));
            $this->saveFileViaScan($file);
        }
    }

    protected function getStorageFiles(string $dir, &$results = []): array
    {
        if (is_dir($dir)) {
            foreach (scandir($dir) as $value) {
                if ($value === "." || $value === "..") {
                    continue;
                }

                $path = $dir . DIRECTORY_SEPARATOR . $value;
                if (is_file($path)) {
                    $results[] = $path;
                } elseif (is_dir($path)) {
                    if (!in_array($value, [self::CHUNKS_DIR, self::TMP_DIR, self::TRASH_DIR])) {
                        $this->getStorageFiles($path, $results);
                    }
                }
            }
        }

        return $results;
    }

    protected function getStorageDirs(string $dir, &$results = []): array
    {
        if (is_dir($dir)) {
            foreach (scandir($dir) as $value) {
                if ($value === "." || $value === "..") {
                    continue;
                }

                $path = $dir . DIRECTORY_SEPARATOR . $value;

                if (in_array($value, [self::CHUNKS_DIR, self::TMP_DIR, self::TRASH_DIR])) {
                    continue;
                }

                if (is_dir($path)) {
                    $results[] = $path;
                    $this->getStorageDirs($path, $results);
                }
            }
        }

        return $results;
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getFileManager(): FileManager
    {
        return $this->container->get('fileManager');
    }

    protected function getPathBuilder(): FilePathBuilder
    {
        return $this->container->get('filePathBuilder');
    }

    public function getMemoryStorage(): StorageInterface
    {
        return $this->container->get('memoryStorage');
    }
}