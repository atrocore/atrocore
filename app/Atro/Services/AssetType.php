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

use Atro\Core\EventManager\Event;
use Atro\Core\Templates\Services\Base;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

class AssetType extends Base
{
    public function massRemove(array $params)
    {
        $params = $this->dispatchEvent('beforeMassRemove', new Event(['params' => $params]))->getArgument('params');

        $ids = [];
        if (array_key_exists('ids', $params) && !empty($params['ids']) && is_array($params['ids'])) {
            $ids = $params['ids'];
        }

        if (array_key_exists('where', $params)) {
            $selectParams = $this->getSelectParams(['where' => $params['where']]);
            $this->getEntityManager()->getRepository($this->getEntityType())->handleSelectParams($selectParams);

            $query = $this
                ->getEntityManager()
                ->getQuery()
                ->createSelectQuery($this->getEntityType(), array_merge($selectParams, ['select' => ['id']]));

            $ids = $this
                ->getEntityManager()
                ->getPDO()
                ->query($query)
                ->fetchAll(\PDO::FETCH_COLUMN);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            foreach ($ids as $id) {
                $this->deleteEntity($id);
            }
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $this->dispatchEvent('afterMassRemove', new Event(['result' => ['count' => count($ids), 'ids' => $ids]]))->getArgument('result');
    }

    public function duplicateValidationRules(Entity $assetType, Entity $duplicatingAssetType): void
    {
        $validationRules = $duplicatingAssetType->get('validationRules');

        if (empty($validationRules) || count($validationRules) === 0) {
            return;
        }

        /** @var \Atro\Repositories\ValidationRule $repository */
        $repository = $this->getEntityManager()->getRepository('ValidationRule');

        foreach ($validationRules as $rule) {
            $entity = $repository->get();
            $entity->set($rule->toArray());
            $entity->id = Util::generateId();
            $entity->set('assetTypeId', $assetType->get('id'));

            try {
                $repository->save($entity);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('ValidationRule duplicating failed: ' . $e->getMessage());
            }
        }
    }
}
