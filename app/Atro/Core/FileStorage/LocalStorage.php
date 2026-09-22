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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Utils\FolderPathGenerator;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Thumbnail;
use Atro\Core\Utils\Xattr;
use Atro\Entities\File;
use Atro\Entities\Folder;
use Atro\Entities\Storage;
use Atro\EntryPoints\Image;
use Doctrine\DBAL\Connection;
use Atro\Core\Utils\FileManager;
use Atro\Core\Utils\Config;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Psr\Http\Message\StreamInterface;

class LocalStorage implements FileStorageInterface, LocalFileStorageInterface, HasBasketInterface, HasVersionHistoryInterface
{
    public const TMP_DIR = 'data/.local-storage-tmp';
    public const CHUNKS_DIR = '.chunks';
    public const TRASH_DIR = '.trash';
    public const PDF_IMAGE_DIR = '.img-from-pdf';
    public const VERSION_DIR = '.versions';

    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * The chunk directory name, as sent by the uploading client. It ends up in a filesystem
     * path, so it has to stay a single harmless segment - the browser widget sends an MD5 hex
     * digest, and anything that could climb out of the chunks directory is rejected outright.
     *
     * @throws BadRequest
     */
    public static function assertChunkHash(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value) !== 1) {
            throw new BadRequest("'fileUniqueHash' is invalid.");
        }

        return $value;
    }

    /**
     * A chunk file name - the byte offset the chunk starts at, which is what the client sends
     * and what scanDir() reads back when the chunks are reassembled.
     *
     * @throws BadRequest
     */
    public static function assertChunkName(mixed $value): string
    {
        if (is_int($value) && $value >= 0) {
            return (string)$value;
        }

        if (!is_string($value) || preg_match('/^\d{1,20}$/', $value) !== 1) {
            throw new BadRequest("'start' is invalid.");
        }

        return $value;
    }

    public static function parseInputFileContent(string $fileContent): string
    {
        $arr      = explode(',', $fileContent);
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

    public function createFile(File $file, string $localPath): bool
    {
        // only generate a path on first creation - a reupload calls createFile() again with the
        // same File entity, and its real on-disk location needs to stay stable across reuploads
        if (!$file->getStorage()->get('syncFolders') && empty($file->get('path'))) {
            $file->set('path', FolderPathGenerator::generate($this->getConfig()->get('uploadRootPath') ?? '', true));
            $file->set('thumbnailsPath', $file->get('path'));
        }

        $fileName = $this->getLocalPath($file);

        $this->getFileManager()->mkdir($this->getFileManager()->getFileDir($fileName), 0777, true);

        // copy, not move: $localPath is caller-owned and reused/deleted by it afterward
        $result = copy($localPath, $fileName);

        if ($result) {
            $mimeType = mime_content_type($fileName);
            if ($mimeType === 'text/plain' && pathinfo($fileName, PATHINFO_EXTENSION) === 'csv') {
                $mimeType = 'text/csv';
            }
            $file->set('fileMtime', gmdate("Y-m-d H:i:s", filemtime($fileName)));
            $file->set('mimeType', $mimeType);
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
        $path      = $this->getChunksDir($storage) . DIRECTORY_SEPARATOR . self::assertChunkHash($input->fileUniqueHash ?? null);
        $chunkName = self::assertChunkName($input->start ?? null);

        $this->getFileManager()->putContents($path . DIRECTORY_SEPARATOR . $chunkName, self::parseInputFileContent($input->piece));

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
        $this->getFileManager()->removeAllInDir($this->getPdfImagesDir($storage));
    }

    public function renameFile(File $file): bool
    {
        $from = $this->getLocalPath($file, true);
        $to   = $this->getLocalPath($file);

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

    public function reupload(File $file, string $localPath): bool
    {
        return $this->deleteFile($file) && $this->createFile($file, $localPath);
    }

    public function deleteFile(File $file): bool
    {
        $file = $file->_fetchedEntity ?? $file;

        /** @var Thumbnail $thumbnailCreator */
        $thumbnailCreator = $this->container->get(Thumbnail::class);

        // delete thumbnails
        $thumbnailCreator->deleteAllThumbnails($file);

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
        $trashDir    = $storagePath . DIRECTORY_SEPARATOR . self::TRASH_DIR;

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

    public function createFileVersion(File $file, string $versionId): bool
    {
        $versionDir = $this->getFileVersionDir($file, $versionId);
        $this->getFileManager()->mkdir($versionDir, 0777, true);

        // use fetched (currently-persisted) values — when called from a beforeSave hook, $file's
        // current attributes may already hold pending, not-yet-applied values (e.g. a new name),
        // while the bytes on disk are still stored under the old one
        return copy($this->getLocalPath($file, true), $versionDir . DIRECTORY_SEPARATOR . $file->getFetched('name'));
    }

    public function getFileVersionContents(File $version): string
    {
        $versionId = $version->_versionId ?? null;
        if (empty($versionId)) {
            throw new Error('Missing version id on the versioned File entity.');
        }

        return file_get_contents($this->getFileVersionLocalPath($version, $versionId));
    }

    public function deleteFileVersion(File $file, string $versionId): bool
    {
        $this->getFileManager()->removeAllInDir($this->getFileVersionDir($file, $versionId));

        return true;
    }

    public function getFileVersionLocalPath(File $file, string $versionId): string
    {
        return $this->getFileVersionDir($file, $versionId) . DIRECTORY_SEPARATOR . $file->get('name');
    }

    protected function getFileVersionDir(File $file, string $versionId): string
    {
        $storagePath = trim($file->getStorage()->get('path'), DIRECTORY_SEPARATOR);

        // syncFolders: group by file id (real folder names already mirror PIM's own tree, so a
        // flat-by-id grouping under .versions is enough). Otherwise real paths are generated/hashed
        // and carry no meaning on their own, so mirror the file's own real path instead - the exact
        // same segment getLocalPath() already uses to locate the file itself.
        $fileSegment = $file->getStorage()->get('syncFolders')
            ? $file->get('id')
            : trim($file->get('path'), DIRECTORY_SEPARATOR);

        return $storagePath . DIRECTORY_SEPARATOR . self::VERSION_DIR . DIRECTORY_SEPARATOR . $fileSegment . DIRECTORY_SEPARATOR . $versionId;
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
        $url = $this->getConfig()->getSiteUrl() . DIRECTORY_SEPARATOR;
        if (in_array($file->get('mimeType'), Image::TYPES)) {
            $url .= 'images' . DIRECTORY_SEPARATOR . $file->get('id') . '.' . $file->get('extension');
        } else {
            $url .= 'downloads' . DIRECTORY_SEPARATOR . $file->get('id') . '.' . $file->get('extension');
        }

        return $url;
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

    public function getThumbnailPdfImageCachePath(File $file): string
    {
        return $this->getPdfImagesDir($file->getStorage()) . DIRECTORY_SEPARATOR . $file->get('id');
    }

    public function isAvailable(Storage $storage): bool
    {
        return true;
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

    public function getChunksDir(Storage $storage): string
    {
        return trim($storage->get('path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::CHUNKS_DIR;
    }

    protected function getPdfImagesDir(Storage $storage): string
    {
        return trim($storage->get('path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::PDF_IMAGE_DIR;
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
            $parts   = explode(DIRECTORY_SEPARATOR, $dir);
            $dirName = array_pop($parts);

            $id = $xattr->get($dir, 'atroId');

            $entityData = [
                'id'       => $id ?? IdGenerator::uuid(),
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
                $entity     = $folderRepository->get();
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
            } catch (UniqueConstraintViolationException|NotUnique $e) {
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
                    if (empty($file->get('width')) || empty($file->get('height')) || empty($file->get('colorSpace'))) {
                        $fileRepo->addDimensions($file);
                        if ($file->isAttributeChanged('width') || $file->isAttributeChanged('height')) {
                            $fileRepo->save($file);
                        }
                    }
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
            $toCreate       = [];
            $toUpdate       = [];
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
                    $prefix  = trim($storage->get('path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                    $dirname = $fileInfo['dirname'];

                    $entityData['path']     = str_starts_with($dirname, $prefix) ? substr($dirname, strlen($prefix)) : $dirname;
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
                        $skip        = true;
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
        } catch (UniqueConstraintViolationException|NotUnique $e) {
            $parts = explode('.', $file->get('name'));
            $ext   = array_pop($parts);
            $from  = $this->getLocalPath($file);
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
                    if (!in_array($value, [self::CHUNKS_DIR, self::TMP_DIR, self::TRASH_DIR, self::VERSION_DIR])) {
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

                if (in_array($value, [self::CHUNKS_DIR, self::TMP_DIR, self::TRASH_DIR, self::VERSION_DIR])) {
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

    public function getMemoryStorage(): StorageInterface
    {
        return $this->container->get('memoryStorage');
    }
}