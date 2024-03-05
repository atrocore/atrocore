<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\FileStorage;

use Atro\Core\Container;
use Atro\Core\Exceptions\NotUnique;
use Atro\Entities\File;
use Atro\Entities\Storage;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;

class FileSystem implements FileStorageInterface
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function scan(Storage $storage): void
    {
        $collection = new EntityCollection([], 'File');

        $files = $this->getDirFiles(trim($storage->get('path'), '/'));
        foreach ($files as $fileName) {
            $fileInfo = pathinfo($fileName);

            if ($fileInfo['basename'] === 'lastCreated') {
                continue;
            }

            $entity = $this->getEntityManager()->getRepository('File')->get();
            $entity->set([
                'name'      => $fileInfo['basename'],
                'path'      => ltrim($fileInfo['dirname'], trim($storage->get('path'), '/') . '/'),
                'size'      => filesize($fileName),
                'hash'      => md5_file($fileName),
                'mimeType'  => mime_content_type($fileName),
                'storageId' => $storage->get('id')
            ]);

            try {
                $this->getEntityManager()->saveEntity($entity);
            } catch (UniqueConstraintViolationException $e) {
            } catch (NotUnique $e) {
            }

            $collection->append($entity);
        }

        /** @var Connection $conn */
        $conn = $this->container->get('connection');

        $conn->createQueryBuilder()
            ->update('file')
            ->set('deleted', ':true')
            ->where('storage_id = :storageId')
            ->andWhere('hash NOT IN (:hash)')
            ->andWhere('deleted = :false')
            ->setParameter('storageId', $storage->get('id'))
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('hash', array_column($collection->toArray(), 'hash'), $conn::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();
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

    public function delete(File $file): void
    {
        //@todo
    }

    public function getLocalPath(File $file): string
    {
        //@todo
        return '';
    }

    public function getUrl(File $file): string
    {
        //@todo
        return '';
    }

    public function getThumbnailUrl(File $file, string $type): string
    {
        //@todo
        return '';
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }
}