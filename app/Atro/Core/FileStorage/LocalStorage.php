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
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Utils\Xattr;
use Atro\Entities\File;
use Atro\Entities\Storage;
use Atro\EntryPoints\Image;
use Doctrine\DBAL\Connection;
use Espo\Core\FilePathBuilder;
use Atro\Core\Utils\FileManager;
use Espo\ORM\EntityManager;

class LocalStorage implements FileStorageInterface, LocalFileStorageInterface
{
    public const CHUNKS_DIR = '.chunks';

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

        /** @var \Atro\Repositories\File $fileRepo */
        $fileRepo = $this->getEntityManager()->getRepository('File');

        $files = $this->getDirFiles(trim($storage->get('path'), '/'));

        $ids = [];

        foreach (array_chunk($files, 20000) as $chunk) {
            $toCreate = [];
            $toUpdate = [];
            $toUpdateByFile = [];

            foreach ($chunk as $fileName) {
                $fileInfo = pathinfo($fileName);
                // ignore system file
                if ($fileInfo['basename'] === 'lastCreated') {
                    continue;
                }
                // ignore chunks
                if (strpos($fileInfo['dirname'], self::CHUNKS_DIR) !== false) {
                    continue;
                }

                $entityData = [
                    'name'      => $fileInfo['basename'],
                    'path'      => ltrim($fileInfo['dirname'], trim($storage->get('path'), '/') . '/'),
                    'fileSize'  => filesize($fileName),
                    'fileMtime' => gmdate("Y-m-d H:i:s", filemtime($fileName)),
                    'hash'      => md5_file($fileName),
                    'mimeType'  => mime_content_type($fileName),
                    'storageId' => $storage->get('id'),
                    '_fileName' => $fileName
                ];

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
                $this->getEntityManager()->saveEntity($entity, ['scanning' => true]);
                $xattr->set($entityData['_fileName'], 'atroId', $entity->get('id'));
                $ids[] = $entity->get('id');
            }

            foreach ($toUpdate as $entity) {
                $this->getEntityManager()->saveEntity($entity, ['scanning' => true]);
            }
        }

        $offset = 0;
        $limit = 30000;
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

            $offset = $offset + $limit;

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

    public function getDirFiles(string $dir, &$results = []): array
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
                    $this->getDirFiles($path, $results);
                }
            }
        }

        return $results;
    }

    public function create(File $file): bool
    {
        $input = $file->_input ?? new \stdClass();

        // create via contents
        if (property_exists($input, 'fileContents')) {
            $file->set('path', $this->getPathBuilder()->createPath($file->get('storage')->get('path') . DIRECTORY_SEPARATOR));

            $fileName = $this->getLocalPath($file);

            // create folders for new file
            $this->getFileManager()->mkdir($this->getFileManager()->getFileDir($fileName), 0777, true);

            if (file_put_contents($fileName, self::parseInputFileContent($input->fileContents))) {
                $file->set('fileMtime', gmdate("Y-m-d H:i:s", filemtime($fileName)));
                $file->set('hash', md5_file($fileName));
                $file->set('mimeType', mime_content_type($fileName));
                $file->set('fileSize', filesize($fileName));

                $xattr = new Xattr();
                $xattr->set($fileName, 'atroId', $file->id);

                return true;
            }
        }

        // create via chunks
        if (property_exists($input, 'allChunks')) {
            $file->set('path', $this->getPathBuilder()->createPath($file->get('storage')->get('path') . DIRECTORY_SEPARATOR));

            $fileName = $this->getLocalPath($file);

            $storage = $this->getEntityManager()->getRepository('Storage')->get($file->get('storageId'));

            $chunkDirPath = $this->getChunksDir($storage) . DIRECTORY_SEPARATOR . $input->fileUniqueHash;

            // create folders for new file
            $this->getFileManager()->mkdir($this->getFileManager()->getFileDir($fileName), 0777, true);

            // create file via chunks
            $f = fopen($fileName, 'a+');
            foreach ($input->allChunks as $chunk) {
                fwrite($f, file_get_contents($chunkDirPath . DIRECTORY_SEPARATOR . $chunk));
            }
            fclose($f);

            $file->set('fileMtime', gmdate("Y-m-d H:i:s", filemtime($fileName)));
            $file->set('hash', md5_file($fileName));
            $file->set('mimeType', mime_content_type($fileName));
            $file->set('fileSize', filesize($fileName));

            $xattr = new Xattr();
            $xattr->set($fileName, 'atroId', $file->id);

            $this->getMemoryStorage()->set("file_{$file->id}", $file);

            return true;
        }

        return false;
    }

    public function getChunksDir(Storage $storage): string
    {
        return trim($storage->get('path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::CHUNKS_DIR;
    }

    /**
     * @inheritDoc
     */
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

    public function rename(File $file): bool
    {
        $from = $this->getLocalPath($file, true);
        $to = $this->getLocalPath($file);

        if (file_exists($from)) {
            return $this->getFileManager()->move($from, $to);
        }

        return false;
    }

    public function delete(File $file): bool
    {
        $path = $this->getLocalPath($file);
        if (file_exists($path)) {
            return $this->getFileManager()->removeFile($path);
        }

        return true;
    }

    public function getContents(File $file): string
    {
        return file_get_contents($this->getLocalPath($file));
    }

    public function getLocalPath(File $file, bool $fetched = false): string
    {
        $method = $fetched ? 'getFetched' : 'get';

        $res = trim($file->get('storage')->get('path'), DIRECTORY_SEPARATOR);

        if (!empty(trim($file->$method('path'), DIRECTORY_SEPARATOR))) {
            $res .= DIRECTORY_SEPARATOR . trim($file->$method('path'));
        }

        return $res . DIRECTORY_SEPARATOR . $file->$method("name");
    }

    public function getUrl(File $file): string
    {
        if (!$file->get('private')) {
            return $this->getLocalPath($file);
        }

        $url = '?entryPoint=';
        if (in_array($file->get('mimeType'), Image::TYPES)) {
            $url .= 'image';
        } else {
            $url .= 'download';
        }
        $url .= "&id={$file->get('id')}";

        return $url;
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