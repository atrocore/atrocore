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

namespace Atro\Core\Templates\Repositories;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\ORM\Repositories\RDB;
use Atro\Core\PseudoTransactionManager;
use Atro\Core\Utils\Util;
use Atro\Services\Record;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Base extends RDB
{
    public function find(array $params = [])
    {
        /** @var EntityCollection $collection */
        $collection = parent::find($params);

        $firstEntity = $collection[0] ?? null;
        if (!empty($firstEntity) && $this->getMetadata()->get("scopes.{$firstEntity->getEntityName()}.hasAttribute")) {
            $this->prepareAttributesForOutput($collection, $params);
        }

        return $collection;
    }

    public function findRelated(Entity $entity, $relationName, array $params = [])
    {
        /** @var EntityCollection $collection */
        $collection = parent::findRelated($entity, $relationName, $params);

        $firstEntity = $collection[0] ?? null;
        if (!empty($firstEntity) && $this->getMetadata()->get("scopes.{$firstEntity->getEntityName()}.hasAttribute")) {
            $this->prepareAttributesForOutput($collection, $params);
        }

        return $collection;
    }

    public function prepareAttributesForOutput(EntityCollection $collection, array $params): void
    {
        if (empty($params['attributesIds']) && empty($params['allAttributes'])) {
            return;
        }

        if (!empty($params['allAttributes'])) {
            foreach ($collection as $entity) {
                $this->getAttributeFieldConverter()->putAttributesToEntity($entity);
            }
        }

        if(empty($params['completeAttrDefs'])) {
            foreach ($collection as $entity) {
                if (!empty($entity->get('attributesDefs'))) {
                    $attributesDefs = [];
                    foreach ($entity->get('attributesDefs') as $field => $defs) {
                        $attributesDefs[$field]['attributeId'] = $defs['attributeId'];
                        $attributesDefs[$field]['type'] = $defs['type'];
                    }
                    $entity->set('attributesDefs', $attributesDefs);
                }
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->has('modifiedAt') && $entity->isAttributeChanged('modifiedAt')) {
            $this->updateMasterRecord($entity);
        }
    }

    protected function updateMasterRecord(Entity $entity): void
    {
        if (!$this->getMetadata()->get("scopes.{$entity->getEntityName()}.primaryEntityId")) {
            return;
        }

        $masterDataEntity = $this->getEntityManager()->getEntity('MasterDataEntity', $entity->getEntityName());
        if (empty($masterDataEntity) && empty($masterDataEntity->get('updateMasterAutomatically'))) {
            return;
        }

        try {
            $this->getInjection('serviceFactory')->create('MasterDataEntity')->updateMasterRecordByStagingEntity($entity);
        } catch (Forbidden|BadRequest $e) {
            // ignore
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        // update modifiedAt for related entities
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'links'], []) as $link => $defs) {
            if (empty($defs['entity']) || empty($defs['relationName']) || empty($defs['foreign'])) {
                continue;
            }
            if (in_array($defs['foreign'], $this->getMetadata()->get(['scopes', $defs['entity'], 'modifiedExtendedRelations'], []))) {
                $entity->loadLinkMultipleField($link);
                foreach ($entity->get($link . 'Ids') ?? [] as $id) {
                    $this->getPseudoTransactionManager()->pushUpdateEntityJob($defs['entity'], $id, [
                        'modifiedAt'   => (new \DateTime())->format('Y-m-d H:i') . ':00',
                        'modifiedById' => $this->getEntityManager()->getUser()->get('id')
                    ]);
                }
            }
        }

        $this->getEntityManager()->getRepository('MatchedRecord')->afterRemoveRecord($entity->getEntityName(), $entity->get('id'));
    }

    public function hasDeletedRecordsToClear(): bool
    {
        if (empty($this->seed)) {
            return false;
        }

        $clearDays = $this->getMetadata()->get(['scopes', $this->entityName, 'clearDeletedAfterDays']) ?? 60;

        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->getConnection()->quoteIdentifier($tableName))
            ->where('deleted=:true')
            ->setParameter('true', true, ParameterType::BOOLEAN);

        $date = new \DateTime();
        if ($clearDays > 0) {
            $date->modify("-{$clearDays} days");
        }
        $date = $date->format('Y-m-d H:i:s');

        if ($this->seed->hasField('modifiedAt')) {
            if ($this->seed->hasField('createdAt')) {
                $qb->andWhere('modified_at<:date OR (modified_at IS NULL AND created_at<:date)');
            } else {
                $qb->andWhere('modified_at<:date OR modified_at IS NULL');
            }
            $qb->setParameter('date', $date);
        } elseif ($this->seed->hasField('createdAt')) {
            $qb->andWhere('created_at<:date OR created_at IS NULL');
            $qb->setParameter('date', $date);
        }

        return !empty($qb->fetchOne());
    }

    public function clearDeletedRecords(): void
    {
        if (empty($this->seed)) {
            return;
        }

        $clearDays = $this->getMetadata()->get(['scopes', $this->entityName, 'clearDeletedAfterDays']) ?? 60;

        $date = new \DateTime();
        if ($clearDays > 0) {
            $date->modify("-{$clearDays} days");
        }
        $date = $date->format('Y-m-d H:i:s');

        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        $qb = $this->getConnection()->createQueryBuilder()
            ->delete($this->getConnection()->quoteIdentifier($tableName))
            ->where('deleted=:true')
            ->setParameter('true', true, ParameterType::BOOLEAN);

        if ($this->seed->hasField('modifiedAt')) {
            if ($this->seed->hasField('createdAt')) {
                $qb->andWhere('modified_at<:date OR (modified_at IS NULL AND created_at<:date)');
            } else {
                $qb->andWhere('modified_at<:date OR modified_at IS NULL');
            }
            $qb->setParameter('date', $date);
        } elseif ($this->seed->hasField('createdAt')) {
            $qb->andWhere('created_at<:date OR created_at IS NULL');
            $qb->setParameter('date', $date);
        }

        $qb->executeQuery();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency(AttributeFieldConverter::class);
        $this->addDependency('language');
        $this->addDependency('pseudoTransactionManager');
        $this->addDependency('serviceFactory');
    }

    protected function getAttributeFieldConverter(): AttributeFieldConverter
    {
        return $this->getInjection(AttributeFieldConverter::class);
    }

    protected function getPseudoTransactionManager(): PseudoTransactionManager
    {
        return $this->getInjection('pseudoTransactionManager');
    }

    protected function translateException(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', $this->entityName);
    }
}
