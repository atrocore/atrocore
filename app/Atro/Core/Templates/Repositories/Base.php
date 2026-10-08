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
use Atro\Core\Exceptions\NotFound;
use Atro\Core\ORM\Repositories\RDB;
use Atro\Core\PseudoTransactionManager;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\Services\Record;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Base extends RDB
{
    protected function getNewEntity()
    {
        $entity = parent::getNewEntity();

        $this->afterEntityPopulated($entity);

        return $entity;
    }

    protected function getEntityById($id)
    {
        $entity = parent::getEntityById($id);

        if ($entity !== null) {
            $this->afterEntityPopulated($entity);
        }

        return $entity;
    }

    protected function afterEntityPopulated(Entity $entity): void
    {
    }

    public function find(array $params = [])
    {
        /** @var EntityCollection $collection */
        $collection = parent::find($params);

        foreach ($collection as $entity) {
            $this->afterEntityPopulated($entity);
        }

        $firstEntity = $collection[0] ?? null;
        if (!empty($firstEntity) && $this->getMetadata()->get("scopes.{$firstEntity->getEntityName()}.hasAttribute")) {
            $this->prepareAttributesForOutput($collection, $params);
        }

        return $collection;
    }

    public function findRelated(Entity $entity, $relationName, array $params = [])
    {
        /** @var EntityCollection $collection */
        $res = parent::findRelated($entity, $relationName, $params);

        if ($res instanceof EntityCollection) {
            $collection = $res;
        } else if (!empty($res)) {
            $collection = new EntityCollection();
            $collection->append($res);
        }

        if (isset($collection)) {
            $firstEntity = $collection[0] ?? null;
            if (!empty($firstEntity) && $this->getMetadata()->get("scopes.{$firstEntity->getEntityName()}.hasAttribute")) {
                $this->prepareAttributesForOutput($collection, $params);
            }
        }

        return $res;
    }

    public function getRecordPosition(string $id, array $selectParams): ?int
    {
        $selectParams['select'] = ['id'];
        unset($selectParams['offset'], $selectParams['limit']);

        $mapper = $this->getMapper();
        $qb     = $mapper->createSelectQueryBuilder($this->get(), $selectParams);

        $order = $qb->getQueryPart('orderBy');
        if (empty($order)) {
            return null;
        }

        $qb->addSelect('ROW_NUMBER() OVER (ORDER BY ' . implode(', ', $order) . ') AS atro_position');
        $qb->resetQueryPart('orderBy');

        $params                 = $qb->getParameters();
        $types                  = $qb->getParameterTypes();
        $params['atroRecordId'] = $id;
        $types['atroRecordId']  = ParameterType::STRING;

        $idAlias = $mapper->getQueryConverter()->fieldToAlias('id');

        $position = $this->getDbal()->fetchOne(
            "SELECT numbered.atro_position FROM ({$qb->getSQL()}) numbered WHERE numbered.$idAlias = :atroRecordId",
            $params,
            $types
        );

        return empty($position) ? null : (int) $position;
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

        if (empty($params['completeAttrDefs'])) {
            foreach ($collection as $entity) {
                if (!empty($entity->get('attributesDefs'))) {
                    $attributesDefs = [];
                    foreach ($entity->get('attributesDefs') as $field => $defs) {
                        $attributesDefs[$field]['attributeId'] = $defs['attributeId'];
                        $attributesDefs[$field]['type']        = $defs['type'];
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
        $masterEntityName = $this->getMetadata()->get("scopes.{$entity->getEntityName()}.primaryEntityId");
        if (!$masterEntityName) {
            return;
        }

        $consolidation = $this->getEntityManager()->getRepository('Consolidation')->getByEntityName($masterEntityName);
        if (empty($consolidation) || empty($consolidation->get('updateMasterAutomatically'))) {
            return;
        }

        try {
            $this->getInjection('serviceFactory')->create('Consolidation')->updateMasterRecord($entity);
        } catch (Forbidden|BadRequest $e) {
            // ignore
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->unlinkAllStagings($entity);

        // update modifiedAt for related entities
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links'], []) as $link => $defs) {
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

        $this->getEntityManager()->getRepository('ClusterItem')->afterRemoveRecord($entity->getEntityName(), $entity->get('id'));

        $this->getEntityManager()->getRepository('SelectionItem')->afterRemoveRecord($entity->getEntityName(), $entity->get('id'));
    }

    /**
     * Unlinks all staging records associated with the given entity by setting
     * the `master_record` field to null where it matches the entity's ID.
     *
     * @param Entity $entity The entity whose associated staging records should be unlinked.
     *
     * @return void
     */
    public function unlinkAllStagings(Entity $entity): void
    {
        foreach ($this->getMetadata()->get("scopes") ?? [] as $scope => $scopeData) {
            if (!empty($scopeData['primaryEntityId']) && $scopeData['primaryEntityId'] === $this->entityName) {
                $this->getDbal()->createQueryBuilder()
                    ->update($this->getDbal()->quoteIdentifier(Util::toUnderScore(lcfirst($scope))))
                    ->set('master_record_id', ':null')
                    ->where('master_record_id=:id')
                    ->setParameter('id', $entity->id)
                    ->setParameter('null', null, ParameterType::NULL)
                    ->executeQuery();
            }
        }
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

    public function hasPersonalDataTable(): bool
    {
        return !empty($this->getMetadata()->get(['scopes', $this->entityName, 'containsPersonalData']))
            && !empty($this->getMetadata()->get(['entityDefs', $this->entityName . 'PersonalData']));
    }

    public function getPersonalDataFields(string $id): array
    {
        if (!$this->hasPersonalDataTable()) {
            return [];
        }

        return $this->getDbal()->createQueryBuilder()
            ->select('field')
            ->from($this->getDbal()->quoteIdentifier($this->getPersonalDataTableName()))
            ->where('record_id = :recordId')
            ->andWhere('deleted = :false')
            ->setParameter('recordId', $id)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();
    }

    public function createPersonalDataRecord(string $id, string $field): void
    {
        $this->validatePersonalDataRecord($id, $field);

        if (in_array($field, $this->getPersonalDataFields($id))) {
            return;
        }

        $this->getDbal()->createQueryBuilder()
            ->insert($this->getDbal()->quoteIdentifier($this->getPersonalDataTableName()))
            ->setValue('id', ':id')
            ->setValue('deleted', ':false')
            ->setValue('record_id', ':recordId')
            ->setValue('field', ':field')
            ->setParameter('id', IdGenerator::uuid())
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('recordId', $id)
            ->setParameter('field', $field)
            ->executeStatement();
    }

    public function deletePersonalDataRecord(string $id, string $field): void
    {
        $this->validatePersonalDataRecord($id, $field);

        $this->getDbal()->createQueryBuilder()
            ->delete($this->getDbal()->quoteIdentifier($this->getPersonalDataTableName()))
            ->where('record_id = :recordId')
            ->andWhere('field = :field')
            ->setParameter('recordId', $id)
            ->setParameter('field', $field)
            ->executeStatement();
    }

    protected function validatePersonalDataRecord(string $id, string $field): void
    {
        if (!$this->hasPersonalDataTable()) {
            throw new BadRequest("Entity '$this->entityName' does not contain personal data.");
        }

        $fieldDefs = $this->getMetadata()->get(['entityDefs', $this->entityName, 'fields', $field], []);
        if (empty($fieldDefs['personalData']) || empty($fieldDefs['notInEveryRecord'])) {
            throw new BadRequest("Field '$field' must be marked as 'Personal Data' and 'Not in every record'.");
        }

        if (empty($this->get($id))) {
            throw new NotFound();
        }
    }

    public function getPersonalDataTableName(): string
    {
        return $this->getEntityManager()->getMapper()->toDb($this->entityName . 'PersonalData');
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
