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
use Atro\Core\Utils\Config;
use Atro\Core\Utils\FileManager;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Thumbnail;
use Atro\Entities\File;
use Atro\Entities\Folder;
use Atro\Entities\Storage;
use Espo\ORM\EntityManager;
use Psr\Http\Message\StreamInterface;

/**
 * Base for remote/upload-based storages. Provides:
 *   - sensible defaults for the folder-hierarchy methods, for storages that have no real
 *     remote folder concept of their own (see createFolder() below);
 *   - browser-chunked-upload buffering (createChunk()/getChunksDir()) - assembling those chunks
 *     into the one local file passed to createFile()/reupload() is Atro\Repositories\File's job;
 *   - a cached Connection resolver + isAvailable(), for storages authenticating through a
 *     Connection entity (see getConnectionField());
 *   - the common DI getters.
 *
 */
abstract class AbstractRemoteFileStorage implements FileStorageInterface
{
    protected Container $container;

    protected array $connectionCache = [];

    protected ?bool $isAvailable = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Local cache directory for this storage's temp files (chunk buffering, upload staging).
     */
    abstract public function getCacheDir(): string;

    /**
     * Name of the Storage entity's belongsTo-Connection link field. Used by getConnection()/isAvailable() below.
     */
    abstract protected function getConnectionField(): string;

    /**
     * This storage organizes assets by its own means (brand/tags/bucket key prefix, ...), not a
     * folder path PIM controls, so there is no remote object to create - PIM folders stay a
     * purely local organizational construct. Override when a real remote folder concept exists.
     */
    public function createFolder(Folder $folder): bool
    {
        return true;
    }

    /**
     * Nothing was ever created remotely for a folder (see createFolder()), so a local
     * rename/move has nothing to propagate.
     */
    public function renameFolder(Folder $folder): bool
    {
        return true;
    }

    public function moveFolder(string $entityId, string $wasParentId, string $becameParentId): bool
    {
        return true;
    }

    /**
     * There's no remote folder object to delete (see createFolder()), so "deleting" one just
     * means cleaning up whatever files were directly inside it, via this storage's own
     * deleteFilePermanently() - not recursive (subfolder recursion is the caller's job, same
     * query shape as Atro\Repositories\Folder::deleteFiles()).
     */
    public function deleteFolderPermanently(Folder $folder): bool
    {
        $offset = 0;
        $limit = 20000;
        while (true) {
            $files = $this->getEntityManager()->getRepository('File')
                ->where(['folderId' => $folder->get('id')])
                ->limit($offset, $limit)
                ->order('id')
                ->find();

            if (empty($files[0])) {
                break;
            }

            foreach ($files as $file) {
                $this->deleteFilePermanently($file);
            }

            $offset += $limit;
        }

        return true;
    }

    /**
     * Buffers one browser-uploaded chunk to local disk and returns the chunk file list so far.
     * Purely about receiving chunks from the browser upload widget - unrelated to how (or
     * whether) the remote backend itself does chunked uploads.
     */
    public function createChunk(\stdClass $input, Storage $storage): array
    {
        $path = $this->getChunksDir($storage) . DIRECTORY_SEPARATOR . $input->fileUniqueHash;

        $this->getFileManager()->putContents(
            $path . DIRECTORY_SEPARATOR . $input->start,
            LocalStorage::parseInputFileContent($input->piece)
        );

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
        $this->getFileManager()->removeAllInDir($this->getCacheDir() . DIRECTORY_SEPARATOR . $storage->get('id'));
    }

    public function getChunksDir(Storage $storage): string
    {
        return $this->getCacheDir() . DIRECTORY_SEPARATOR . $storage->get('id') . DIRECTORY_SEPARATOR . '.chunks';
    }

    public function getThumbnailPdfImageCachePath(File $file): ?string
    {
        return $this->getCacheDir() . DIRECTORY_SEPARATOR . $file->getStorage()->get('id') . DIRECTORY_SEPARATOR . $file->get('id');
    }

    /**
     * Resolves (and caches, per storage id) this storage's Connection through connectionFactory.
     * The return shape is whatever the concrete ConnectionType::connect() produces - an SDK
     * client object, a token+domain array, etc. - so this stays untyped.
     */
    protected function getConnection(Storage $storage)
    {
        if (empty($this->connectionCache[$storage->get('id')])) {
            $connectionEntity = $storage->get($this->getConnectionField());
            $this->connectionCache[$storage->get('id')] = $this
                ->container
                ->get('connectionFactory')
                ->create($connectionEntity)
                ->connect($connectionEntity);
        }

        return $this->connectionCache[$storage->get('id')];
    }

    public function isAvailable(Storage $storage): bool
    {
        if ($this->isAvailable !== null) {
            return $this->isAvailable;
        }

        try {
            $connectionEntity = $storage->get($this->getConnectionField());
            $this->isAvailable = $this->container->get('connectionFactory')
                ->create($connectionEntity)
                ->testConnection($connectionEntity);
        } catch (\Throwable $e) {
            $this->isAvailable = false;
        }

        return $this->isAvailable;
    }

    public function getStream(File $file): StreamInterface
    {
        return \GuzzleHttp\Psr7\Utils::streamFor($this->getContents($file));
    }

    public function getThumbnail(File $file, string $size): ?string
    {
        $tc = $this->getThumbnailCreator();
        if ($tc->hasThumbnail($file, $size)) {
            return $tc->preparePath($file, $size);
        }

        return $tc->getPath($file, $size);
    }

    protected function getThumbnailCreator(): Thumbnail
    {
        return $this->container->get(Thumbnail::class);
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getFileManager(): FileManager
    {
        return $this->container->get('fileManager');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }

    protected function translate(string $key, string $label, string $scope = 'Global'): string
    {
        return $this->getLanguage()->translate($key, $label, $scope);
    }
}
