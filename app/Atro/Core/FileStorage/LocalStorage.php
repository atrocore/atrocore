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
                if ($fileInfo['basename'] === 'lastCreated') {
                    continue;
                }

                $entity = $fileRepo->get();
                $entity->set([
                    'name'      => $fileInfo['basename'],
                    'path'      => ltrim($fileInfo['dirname'], trim($storage->get('path'), '/') . '/'),
                    'fileSize'  => filesize($fileName),
                    'fileMtime' => gmdate("Y-m-d H:i:s", filemtime($fileName)),
                    'hash'      => md5_file($fileName),
                    'mimeType'  => mime_content_type($fileName),
                    'storageId' => $storage->get('id')
                ]);
                $entity->_fileName = $fileName;

                $id = $xattr->get($fileName, 'atroId');
                if (empty($id)) {
                    $toCreate[] = $entity;
                } else {
                    $toUpdateByFile[$id] = $entity;
                }
            }

            if (!empty($toUpdateByFile)) {
                $exists = [];
                foreach ($fileRepo->where(['id' => array_keys($toUpdateByFile)])->find() as $v) {
                    $exists[$v->get('id')] = $v;
                }
                foreach ($toUpdateByFile as $k => $v) {
                    if (isset($exists[$k])) {
                        $skip = true;
                        foreach ($v->toArray() as $field => $val) {
                            if ($exists[$k]->get($field) !== $val) {
                                $skip = false;
                            }
                        }

                        $ids[] = $k;

                        if (!$skip) {
                            $toUpdate[$k] = $exists[$k];
                            $toUpdate[$k]->set($v->toArray());
                            $toUpdate[$k]->_fileName = $v->_fileName;
                        }
                    } else {
                        $toCreate[] = $v;
                    }
                }
            }

            foreach ($toCreate as $entity) {
                $this->getEntityManager()->saveEntity($entity);
                $xattr->set($entity->_fileName, 'atroId', $entity->get('id'));
                $ids[] = $entity->get('id');
            }

            foreach ($toUpdate as $entity) {
                $this->getEntityManager()->saveEntity($entity);
            }
        }

//        $offset = 0;
//        $limit = 30000;
//        while (true) {
//            $res = $fileRepo
//                ->select(['id'])
//                ->limit($offset, $limit)
//                ->order('createdAt')
//                ->find();
//
//            if (empty($res[0])) {
//                break;
//            }
//
//            $offset = $offset + $limit;
//
//            $diff = array_diff(array_column($res->toArray(), 'id'), $ids);
//            if (!empty($diff)) {
//                $conn = $this->getEntityManager()->getConnection();
//                foreach (array_chunk($diff, 20000) as $chunk) {
//                    $conn->createQueryBuilder()
//                        ->delete('file')
//                        ->where('storage_id = :storageId')
//                        ->andWhere('id IN (:ids)')
//                        ->andWhere('deleted = :false')
//                        ->setParameter('storageId', $storage->get('id'))
//                        ->setParameter('true', true, ParameterType::BOOLEAN)
//                        ->setParameter('ids', array_values($chunk), $conn::PARAM_STR_ARRAY)
//                        ->setParameter('false', false, ParameterType::BOOLEAN)
//                        ->executeQuery();
//                }
//            }
//        }
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