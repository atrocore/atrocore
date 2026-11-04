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

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\ReferenceData;
use Atro\Core\Twig\Twig;
use Espo\ORM\Entity;
use Espo\ORM\Entity as OrmEntity;
use Espo\ORM\EntityCollection;

class EntityField extends ReferenceData
{
    public function updateEntity(string $id, \stdClass $data): bool
    {
        // the field is managed by the system, e.g. the "type" field of an entity with subtypes
        $field = $this->getRepository()->get($id);
        if (!empty($field) && $this->getMetadata()->get("entityDefs.{$field->get('entityId')}.fields.{$field->get('code')}.customizable") === false) {
            throw new Forbidden();
        }

        return parent::updateEntity($id, $data);
    }

    /**
     * Returns the `where` filter of a link field with Twig expressions resolved against the specified record. The
     * type of the field is the type of the subtype of the service, e.g. "link" for "EntityFieldTypeLink".
     *
     * @throws NotFound   When the field of this type or the record does not exist.
     * @throws Forbidden  When the caller lacks read access to the entity scope, the field, or the record.
     */
    public function prepareFieldWhere(string $id, string $recordId): array
    {
        $field = $this->getRepository()->get($id);
        if (!empty($field)) {
            return $this->prepareFieldWhereByEntityAndField((string)$field->get('entityId'), (string)$field->get('code'), $recordId);
        }

        // the fields hidden from the Entity Manager have no record, the entity and the field are taken from the ID
        $parts = explode('_', $id, 2);
        if (count($parts) !== 2) {
            throw new NotFound();
        }

        $type = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', 'type', 'default']);
        if (empty($type) || $this->getMetadata()->get(['entityDefs', $parts[0], 'fields', $parts[1], 'type']) !== $type) {
            throw new NotFound();
        }

        return $this->prepareFieldWhereByEntityAndField($parts[0], $parts[1], $recordId);
    }

    public function prepareFieldWhereByEntityAndField(string $entityName, string $fieldCode, string $recordId): array
    {
        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        $forbiddenFields = $this->getAcl()->getScopeForbiddenFieldList($entityName, 'read');
        if (in_array($fieldCode, $forbiddenFields, true)) {
            throw new Forbidden();
        }

        $where = $this->getMetadata()->get(['entityDefs', $entityName, 'fields', $fieldCode, 'where']) ?? [];

        if (empty($where) || $recordId === '') {
            return $where;
        }

        $record = $this->getEntityManager()->getRepository($entityName)->get($recordId);
        if (empty($record)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->checkEntity($record, 'read')) {
            throw new Forbidden();
        }

        $renderedWhere = @json_decode(
            $this->twig()->renderTemplate(json_encode($where), ['entity' => $record]),
            true
        );

        return is_array($renderedWhere) ? $renderedWhere : $where;
    }

    public function updateOptionCode(string $id, string $oldValue, string $newValue): bool
    {
        return $this->getRepository()->updateOptionCode($id, $oldValue, $newValue);
    }

    public function resetToDefault(string $id): bool
    {
        $entity = $this->getRepository()->get($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        if (!empty($entity->get('isCustom'))) {
            throw new Error("Can't reset to defaults custom entity field '{$entity->get('code')}'.");
        }

        $this->getEntityManager()->removeEntity($entity);

        return true;
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        foreach ($collection as $entity) {
            $entity->_collectionPrepared = true;
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $main = $this->getEntityManager()->getRepository('Entity')->get($entity->get('entityId'));
        if (!empty($main)) {
            $entity->set('entityData', $main->toArray());
            $entity->set('entityContainsPersonalData', $this->getRepository()->containsPersonalData($entity));
        }
        if (empty($entity->_collectionPrepared)) {
            $this->prepareFileTypesField($entity);
            $this->prepareDefaultField($entity);
        }

        $entity->set('customizable', true);
        if ($this->getMetadata()->get("scopes.{$entity->get('entityId')}.customizable") === false) {
            $entity->set('customizable', false);
        }

        // the field is managed by the system, e.g. the "type" field of an entity with subtypes
        if ($this->getMetadata()->get("entityDefs.{$entity->get('entityId')}.fields.{$entity->get('code')}.customizable") === false) {
            $entity->set('customizable', false);
            $entity->setAvailableAction('edit', false);
        }

        $entityId        = $entity->get('entityId');
        $primaryEntityId = $this->getMetadata()->get("scopes.$entityId.primaryEntityId");
        if (!empty($primaryEntityId)
            && !empty($this->getMetadata()->get("entityDefs.$primaryEntityId.fields.{$entity->get('code')}.type"))
        ) {
            if ($entity->get('isCustom')) {
                $entity->set('isCustom', false);
                $entity->setAvailableAction('delete', false);
            }
            $entity->setAvailableAction('edit', false);
        }
    }

    /**
     * The fields of the system and of the modules cannot be deleted, their configuration can be reset to default
     * unless the entity is custom. The ID field is managed by the system.
     */
    public function putAclMeta(OrmEntity $entity): void
    {
        parent::putAclMeta($entity);

        $isCustom = !empty($entity->get('isCustom'));
        $isCustomEntity = !empty($this->getMetadata()->get(['scopes', $entity->get('entityId'), 'isCustom']));

        $entity->setAvailableAction('resetToDefault', !$isCustom && !$isCustomEntity);
        if (!$isCustom) {
            $entity->setAvailableAction('delete', false);
        }

        if ($entity->get('code') === 'id') {
            $entity->setAvailableAction('edit', false);
            $entity->setAvailableAction('resetToDefault', false);
        }
    }

    public function putAclMetaForLink(OrmEntity $entityFrom, string $link, OrmEntity $entity): void
    {
        $entityName = $entityFrom->getEntityName();
        $isEntity = $entityName === 'Entity' || $this->getMetadata()->get(['scopes', $entityName, 'supertypeId']) === 'Entity';
        if (!$isEntity || $link !== 'fields') {
            parent::putAclMetaForLink($entityFrom, $link, $entity);
            return;
        }

        $this->putAclMeta($entity);
    }


    protected function prepareFileTypesField(Entity $entity): void
    {
        if (empty($entity->get('fileTypes'))) {
            return;
        }

        $fileTypes = $this->getEntityManager()->getRepository('FileType')
            ->where(['id' => $entity->get('fileTypes')])
            ->find();

        $fileTypesNames = [];
        foreach ($fileTypes as $fileType) {
            $fileTypesNames[$fileType->get('id')] = $fileType->get('name');
        }
        $entity->set('fileTypesNames', $fileTypesNames);
    }

    /**
     * The names of the records chosen as the default value and as the default unit.
     */
    protected function prepareDefaultField(Entity $entity): void
    {
        if (!empty($id = $entity->get('defaultUnitId'))) {
            $unit = $this->getEntityManager()->getRepository('Unit')->get($id);
            $entity->set('defaultUnitName', empty($unit) ? null : $unit->get('name'));
        }

        $foreignEntity = null;
        switch ($entity->get('type')) {
            case 'link':
            case 'linkMultiple':
                $foreignEntity = $entity->get('foreignEntityId');
                break;
            case 'measure':
                $foreignEntity = 'Unit';
                break;
            case 'file':
                $foreignEntity = 'File';
                break;
        }

        if (empty($foreignEntity)) {
            return;
        }

        $repository = $this->getEntityManager()->getRepository($foreignEntity);
        if (!empty($ids = $entity->get('defaultIds'))) {
            $defaultNames = [];
            foreach ($repository->where(['id' => $ids])->find() as $foreign) {
                $defaultNames[$foreign->get('id')] = $foreign->get('name');
            }
            $entity->set('defaultNames', $defaultNames);
        } elseif (!empty($id = $entity->get('defaultId')) && !empty($foreign = $repository->get($id))) {
            $entity->set('defaultName', $foreign->get('name'));
        }
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('twig');
        $this->addDependency('dataManager');
    }

    protected function twig(): Twig
    {
        return $this->getInjection('twig');
    }
}
