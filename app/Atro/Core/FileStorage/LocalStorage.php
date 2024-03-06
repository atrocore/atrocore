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
use Atro\Core\Utils\Xattr;
use Atro\Entities\File;
use Atro\Entities\Storage;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;

class LocalStorage implements FileStorageInterface
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function scan(Storage $storage): void
    {
        $xattr = new Xattr();

        /** @var Connection $conn */
        $conn = $this->container->get('connection');

        $ids = [];

        $files = $this->getDirFiles(trim($storage->get('path'), '/'));
        foreach ($files as $fileName) {
            $fileInfo = pathinfo($fileName);

            if ($fileInfo['basename'] === 'lastCreated') {
                continue;
            }

            $id = $xattr->get($fileName, 'atroId');

            $entity = $this->getEntityManager()->getRepository('File')->get();
            $entity->set([
                'name'      => $fileInfo['basename'],
                'path'      => ltrim($fileInfo['dirname'], trim($storage->get('path'), '/') . '/'),
                'size'      => filesize($fileName),
                'hash'      => md5_file($fileName),
                'mimeType'  => mime_content_type($fileName),
                'storageId' => $storage->get('id')
            ]);

            if (empty($id)) {
                $this->getEntityManager()->saveEntity($entity);
                $id = $entity->get('id');
                $xattr->set($fileName, 'atroId', $id);
            } else {
                $conn->createQueryBuilder()
                    ->update('file')
                    ->set('name', ':name')
                    ->set('path', ':path')
                    ->set('size', ':size')
                    ->set('hash', ':hash')
                    ->set('mime_type', ':mimeType')
                    ->set('storage_id', ':storageId')
                    ->where('id=:id')
                    ->setParameter('name', $entity->get('name'))
                    ->setParameter('path', $entity->get('path'))
                    ->setParameter('size', $entity->get('size'))
                    ->setParameter('hash', $entity->get('hash'))
                    ->setParameter('mimeType', $entity->get('mimeType'))
                    ->setParameter('storageId', $entity->get('storageId'))
                    ->setParameter('id', $id)
                    ->executeQuery();
            }
            $ids[] = $id;
        }

        // delete trash data
        $conn->createQueryBuilder()
            ->update('file')
            ->set('deleted', ':true')
            ->where('storage_id = :storageId')
            ->andWhere('id NOT IN (:ids)')
            ->andWhere('deleted = :false')
            ->setParameter('storageId', $storage->get('id'))
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('hash', $ids, $conn::PARAM_STR_ARRAY)
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