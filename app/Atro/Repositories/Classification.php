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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Hierarchy;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class Classification extends Hierarchy
{
    public function getLinkedWithAttributeGroup(array $classificationsIds, ?string $attributeGroupId): array
    {
        $data = $this
            ->getEntityManager()
            ->getRepository('ClassificationAttribute')
            ->select(['id'])
            ->distinct()
            ->join('attribute')
            ->where(
                [
                    'classificationId'           => $classificationsIds,
                    'attribute.attributeGroupId' => ($attributeGroupId != '') ? $attributeGroupId : null
                ]
            )
            ->find()
            ->toArray();

        return array_column($data, 'id');
    }

    public function entityHasMultipleClassifications(string $entityName): bool
    {
        $record = $this->getMultipleClassificationsQb($entityName)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return !empty($record);
    }

    public function getMultipleClassificationsQb(string $entityName): \Doctrine\DBAL\Query\QueryBuilder
    {
        $mapper = $this->getEntityManager()->getMapper();
        $entityColumn = $mapper->toDb(lcfirst("{$entityName}Id"));

        return $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select($entityColumn)
            ->from($mapper->toDb("{$entityName}Classification"), 'ec')
            ->where('ec.deleted = :false')
            ->groupBy($entityColumn)
            ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->having('COUNT(*) > 1');
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('entityId')) {
            throw new BadRequest($this->exception('entityCannotBeChanged'));
        }

        if ($entity->isAttributeChanged('entityId')) {
            if (
                !$this->getMetadata()->get("scopes.{$entity->get('entityId')}.hasClassification")
                || $this->getMetadata()->get("scopes.{$entity->get('entityId')}.primaryEntityId")
            ) {
                throw new BadRequest($this->exception('entityNotAllowToChoose'));
            }
        }

        parent::beforeSave($entity, $options);
    }

    public function save(Entity $entity, array $options = [])
    {
        try {
            $result = parent::save($entity, $options);
        } catch (UniqueConstraintViolationException $e) {
            throw new BadRequest(sprintf($this->exception('notUniqueValue', 'Global'), 'code'));
        }

        return $result;
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($this->hasRelatedRecords($entity)) {
            throw new BadRequest($this->getLanguage()->translate('classificationHasRecords', 'exceptions', 'Classification'));
        }

        parent::beforeRemove($entity, $options);
    }

    public function hasRelatedRecords(Entity $entity): bool
    {
        if (empty($entity->get('entityId'))) {
            return false;
        }

        $relTable = $this->getMapper()->toDb(lcfirst($entity->get('entityId') . 'Classification'));
        $entityColumn = $this->getMapper()->toDb(lcfirst($entity->get('entityId') . 'Id'));
        $entityTable = $this->getMapper()->toDb(lcfirst($entity->get('entityId')));

        $record = $this->getConnection()->createQueryBuilder()
            ->select('ec.id')
            ->from($relTable, 'ec')
            ->innerJoin('ec', $entityTable, 'e', "e.id = ec.$entityColumn AND e.deleted = :false")
            ->where('ec.classification_id = :classification_id')
            ->andWhere('ec.deleted = :false')
            ->setParameter('classification_id', $entity->get('id'))
            ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return !empty($record);
    }

    public function remove(Entity $entity, array $options = [])
    {
        if (!empty($toDelete = $this->getDuplicateEntity($entity, true))) {
            $this->deleteFromDb($toDelete->get('id'), true);
        }

        return parent::remove($entity, $options);
    }

    public function getDuplicateEntity(Entity $entity, bool $deleted = false): ?Entity
    {
        return $this
            ->where([
                'id!='    => $entity->get('id'),
                'release' => $entity->get('release'),
                'code'    => $entity->get('code'),
                'deleted' => $deleted
            ])
            ->findOne(['withDeleted' => $deleted]);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getEntityManager()->getRepository('ClassificationAttribute')
            ->where(['classificationId' => $entity->get('id')])
            ->removeCollection();
    }

    protected function exception(string $key, string $scope = 'Attribute'): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', $scope);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
