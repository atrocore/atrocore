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

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Core\EventManager\Manager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Utils\Note as NoteUtil;
use Atro\Core\Utils\NotificationManager;
use Espo\ORM\Entity as OrmEntity;

class Entity extends AbstractListener
{
    public function beforeSave(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'beforeSave', $event);

        /** @var OrmEntity $entity */
        $entity = $event->getArgument('entity');

        $this->validateClassificationAttributesForRecord($entity);

        $this->recalculateScriptField($entity);
    }

    public function afterSave(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'afterSave', $event);

        /** @var OrmEntity $entity */
        $entity = $event->getArgument('entity');

        $this->getNoteUtil()->afterEntitySaved($entity);

        $this->getNotificationManager()->afterEntitySaved($entity);

        $this->getContainer()->get('realtimeManager')->afterEntityChanged($entity);

        // create classification attributes if it needs
        $this->createClassificationAttributesForRecord($entity);

        // find matchings if it needs
        $this->getContainer()->get('matchingManager')->findMatchingsAfterEntitySave($entity);
    }

    public function beforeRemove(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'beforeRemove', $event);
    }

    public function afterRemove(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'afterRemove', $event);

        $this->getNoteUtil()->afterEntityRemoved($event->getArgument('entity'));

        $this->getNotificationManager()->afterEntityDeleted($event->getArgument('entity'));

    }

    public function beforeMassRelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'beforeMassRelate', $event);
    }

    public function afterMassRelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'afterMassRelate', $event);
    }

    public function beforeRelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'beforeRelate', $event);
    }

    public function afterRelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'afterRelate', $event);
    }

    public function beforeUnrelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'beforeUnrelate', $event);
    }

    public function afterUnrelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'afterUnrelate', $event);

        /** @var OrmEntity $entity */
        $entity = $event->getArgument('entity');

        /** @var OrmEntity $classification */
        $classification = $event->getArgument('foreign');
        $relationName = $event->getArgument('relationName');

        if ($relationName === 'classifications') {
            if (is_string($classification)) {
                $classification = $this->getEntityManager()->getRepository('Classification')->get($classification);
            }
            $this->deleteAttributeValuesFromRecord($entity, $classification);
        }
    }

    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }

    private function getNoteUtil(): NoteUtil
    {
        return $this->getContainer()->get(NoteUtil::class);
    }

    protected function getNotificationManager(): NotificationManager
    {
        return $this->getContainer()->get(NotificationManager::class);
    }

    protected function validateClassificationAttributesForRecord(OrmEntity $entity): void
    {
        $entityName = $this->getMetadata()->get("scopes.{$entity->getEntityName()}.classificationForEntity");
        if (empty($entityName)) {
            return;
        }

        $classification = $this->getEntityManager()->getRepository('Classification')->get($entity->get('classificationId'));
        if (empty($classification)) {
            throw new NotFound();
        }

        $checkEntity = $this->getMetadata()->get("scopes.$entityName.primaryEntityId") ?? $entityName;

        if ($classification->get('entityId') !== $checkEntity) {
            throw new BadRequest($this->getLanguage()->translate('classificationForToAnotherEntity', 'exceptions', 'Classification'));
        }

        $this->validateSingleClassification($entityName, $entity);
    }

    protected function validateSingleClassification(string $entityName, OrmEntity $entity): void
    {
        if (
            !$this->getMetadata()->get(['scopes', $entityName, 'hasClassification'], false)
            || !$this->getMetadata()->get(['scopes', $entityName, 'singleClassification'], false)
            || !$entity->isNew()
        ) {
            return;
        }

        $entityField = lcfirst($entityName) . 'Id';
        $entityId = $entity->get($entityField);

        $record = $this->getEntityManager()->getRepository($entity->getEntityName())
            ->where([
                $entityField => $entityId,
                'deleted'    => false
            ])
            ->findOne();

        if (!empty($record)) {
            throw new BadRequest($this->getLanguage()->translate('singleClassificationAllowed', 'exceptions'));
        }
    }

    protected function createClassificationAttributesForRecord(OrmEntity $entity): void
    {
        $entityName = $this->getMetadata()->get("scopes.{$entity->getEntityName()}.classificationForEntity");
        if (empty($entityName)) {
            return;
        }

        $this->getService('Attribute')->createAttributeValuesFromClassification($entity->get('classificationId'), $entityName, $entity->get(lcfirst($entityName) . 'Id'));
    }

    protected function deleteAttributeValuesFromRecord(OrmEntity $entity, OrmEntity $classification): void
    {
        $entityName = $entity->getEntityName();
        $entityId = $entity->get('id');
        $classificationId = $classification->get('id');

        if (
            !$this->getMetadata()->get(['scopes', $entity->getEntityName(), 'hasAttribute'])
            || !$this->getMetadata()->get(['scopes', $entity->getEntityName(), 'hasClassification'])
            || (!$this->getMetadata()->get(['scopes', $entity->getEntityName(), 'disableAttributeLinking'])
                && !$this->getMetadata()->get(['scopes', $entity->getEntityName(), 'deleteValuesAfterUnlinkingClassification'])
            )
        ) {
            return;
        }

        $repository = $this->getEntityManager()->getRepository('ClassificationAttribute');
        $attributeIds = $repository->getAttributesToRemoveWithClassification($entityName, $entityId, $classificationId);
        if (empty($attributeIds)) {
            return;
        }

        $attributeRepository = $this->getEntityManager()->getRepository('Attribute');
        foreach ($attributeIds as $attributeId) {
            $attributeRepository->removeAttributeValue($entityName, $entityId, $attributeId);
        }
    }

    protected function recalculateScriptField(OrmEntity $entity): void
    {
        if ($this->getMetadata()->get(['scopes', $entity->getEntityName(), 'type']) === 'ReferenceData') {
            return;
        }

        $this->getEntityManager()->getRepository($entity->getEntityType())->calculateScriptFields($entity, false);
    }
}
