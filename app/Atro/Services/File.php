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

declare(strict_types=1);

namespace Atro\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Core\Templates\Services\Base;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

class File extends Base
{
    protected $mandatorySelectAttributeList = ['storageId', 'path', 'thumbnailsPath'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $fileNameParts = explode('.', $entity->get('name'));

        $entity->set('extension', strtolower(array_pop($fileNameParts)));
        $entity->set('downloadUrl', $entity->getDownloadUrl());
        $entity->set('smallThumbnailUrl', $entity->getSmallThumbnailUrl());
        $entity->set('mediumThumbnailUrl', $entity->getMediumThumbnailUrl());
        $entity->set('largeThumbnailUrl', $entity->getLargeThumbnailUrl());
    }

    public function createEntity($attachment)
    {
        // for single upload
        if (!property_exists($attachment, 'piecesCount')) {
            if (empty($attachment->id)) {
                $attachment->id = Util::generateId();
            }

            return $this->createFileEntity($attachment);
        }

        if (empty($attachment->id)) {
            throw new BadRequest("ID is required if create via chunks.");
        }

        // create entity for validation
        $entity = $this->getRepository()->get();
        $entity->set($attachment);

        // validate required fields
        $this->checkRequiredFields($entity, $attachment);

        // validate fields by patterns
        $this->checkFieldsWithPattern($entity);

        $storageEntity = $this->getEntityManager()->getRepository('Storage')->get($entity->get('storageId'));
        if (empty($storageEntity)) {
            throw new BadRequest(
                sprintf($this->getInjection('language')->translate('fieldIsRequired', 'exceptions'), $this->getInjection('language')->translate('storage', 'fields', 'File'))
            );
        }

        /** @var FileStorageInterface $storage */
        $storage = $this->getInjection('container')->get($storageEntity->get('type') . 'Storage');

        $chunks = $storage->createChunk($attachment, $storageEntity);

        $result = [];
        if (count($chunks) === $attachment->piecesCount) {
            $attachment->allChunks = $chunks;
            try {
                $result = $this->createFileEntity($attachment);
            } catch (NotUnique $e) {
                $result['created'] = true;
            } catch (\Throwable $e) {
                // try to delete file
                if (!empty($fileEntity = $this->getMemoryStorage()->get("file_{$attachment->id}"))) {
                    $storage->delete($fileEntity);
                }
                throw $e;
            }
        }

        return array_merge($result, ['chunks' => $chunks]);
    }

    protected function createFileEntity(\stdClass $attachment): array
    {
        $entity = parent::createEntity($attachment);
        $result = $entity->toArray();

        if (!empty($entity->get('hash'))) {
            $duplicate = $this->getRepository()->where(['hash' => $entity->get('hash'), 'id!=' => $entity->get('id')])->findOne();
            if (!empty($duplicate)) {
                $result['duplicate'] = $duplicate->toArray();
            }
        }

        return $result;
    }

    public function getChildren(string $parentId, array $params): array
    {
//        $result = [];
//        $selectParams = $this->getSelectParams($params);
//        $records = $this->getRepository()->getChildrenArray($parentId, true, $params['offset'], $params['maxSize'], $selectParams);
//        if (empty($records)) {
//            return $result;
//        }
//
//        $offset = $params['offset'];
//        $total = $this->getRepository()->getChildrenCount($parentId, $selectParams);
//        $ids = [];
//        foreach ($this->getRepository()->where(['id' => array_column($records, 'id')])->find() as $entity) {
//            if ($this->getAcl()->check($entity, 'read')) {
//                $ids[] = $entity->get('id');
//            }
//        }
//
//        foreach ($records as $k => $record) {
//            $result[] = [
//                'id'             => $record['id'],
//                'name'           => $record['name'],
//                'offset'         => $offset + $k,
//                'total'          => $total,
//                'disabled'       => !in_array($record['id'], $ids),
//                'load_on_demand' => !empty($record['childrenCount']) && $record['childrenCount'] > 0
//            ];
//        }

        return [
            'list'  => [],
            'total' => 0
        ];
    }

    public function getTreeDataForSelectedNode(string $id): array
    {
//        $treeBranches = [];
//        $this->createTreeBranches($this->getEntity($id), $treeBranches);
//
//        if (empty($entity = $treeBranches[0])) {
//            throw new NotFound();
//        }
//
//        $tree = [];
//        $this->prepareTreeForSelectedNode($entity, $tree);
//        $this->prepareTreeData($tree);

//        $total = empty($tree[0]['total']) ? 0 : $tree[0]['total'];

        return ['total' => 0, 'list' => []];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
