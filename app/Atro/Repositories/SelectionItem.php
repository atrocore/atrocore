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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;


class SelectionItem extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {

        if ($this->getMetadata()->get(['scopes', $entity->get('entityName'), 'selectionDisabled'])) {
            throw new BadRequest(str_replace('%s', $entity->get('entityName'), $this->getLanguage()->translate('selectionDisabledForEntity', 'messages', 'SelectionItem')));
        }

        $record = $this->getEntityManager()->getRepository($entity->get('entityName'))
            ->select(['id'])
            ->where(['id' => $entity->get('entityId')])
            ->findOne();

        if (empty($record)) {
            throw new Error("Record in selection Item not found");
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isNew()) {
            $this->getEntityManager()->getRepository('Selection')->createActivityNote(
                $entity->get('selectionId'), 'Selection', 'SelectionActivity', 'linked',
                $entity->get('entityName'), $entity->get('entityId'),
                ['entityRole' => $this->getEntityRole($entity->get('entityName'))]
            );
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getEntityManager()->getRepository('Selection')->createActivityNote(
            $entity->get('selectionId'), 'Selection', 'SelectionActivity', 'unlinked',
            $entity->get('entityName'), $entity->get('entityId'),
            ['entityRole' => $this->getEntityRole($entity->get('entityName'))]
        );
    }

    public function save(Entity $entity, array $options = [])
    {
        try {
            return parent::save($entity, $options);
        } catch (NotUnique $e) {
            throw new NotUnique("Selection record already exists");
        }
    }

    public function afterRemoveRecord(string $entityName, string $entityId): void
    {
        $affectedSelectionIds = $this->getDbal()->createQueryBuilder()
            ->select('selection_id')
            ->from('selection_item')
            ->where('entity_name=:entityName AND entity_id=:entityId AND deleted=:false')
            ->setParameter('entityName', $entityName)
            ->setParameter('entityId', $entityId)
            ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->fetchFirstColumn();

        if (empty($affectedSelectionIds)) {
            return;
        }
        
        foreach ($affectedSelectionIds as $selectionId) {
            $this->getEntityManager()->getRepository('Selection')->createActivityNote(
                $selectionId, 'Selection', 'SelectionActivity', 'deleted', $entityName, $entityId
            );
        }
    }

    public function hasDeletedRecordsToClear(): bool
    {
        return true;
    }

    public function clearDeletedRecords(): void
    {
        parent::clearDeletedRecords();

        $records = $this->getDbal()->createQueryBuilder()
            ->select('entity_name')
            ->distinct()
            ->from('selection_item')
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $entityName = $record['entity_name'];
            $tableName = $this->getDbal()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName)));

            $this->getDbal()->createQueryBuilder()
                ->delete('selection_item', 'ci')
                ->where("ci.entity_name=:entityName AND NOT EXISTS (SELECT 1 FROM $tableName e WHERE e.id=ci.entity_id)")
                ->setParameter('entityName', $entityName)
                ->executeQuery();
        }
    }

    private function getEntityRole(string $entityName): string
    {
        return !empty($this->getMetadata()->get("scopes.$entityName.primaryEntityId")) ? 'staging' : 'master';
    }
}
