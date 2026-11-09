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

namespace Atro\Core\LinkedRecords;

use Atro\Core\Container;
use Atro\Services\AbstractService;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

/**
 * Puts the full records of the link and linkMultiple fields and attributes listed by the Meta-Linked-Fields header to
 * the linkedRecords meta group of the records, keyed by the name of the field. The records of a collection are loaded
 * with one query per linked entity, and each linked record is prepared once, however many records refer to it.
 */
class LinkedRecordsLoader
{
    /** @var array<string, array<string, array{entity: string, multiple: bool}>> */
    private array $fieldDefs = [];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * The attributes of the record that hold the ids of the requested linked records, so that they are selected.
     *
     * @return string[]
     */
    public function getIdAttributes(Entity $seed): array
    {
        $result = [];
        foreach (array_keys($this->getFieldList($seed, AbstractService::getRequestedLinkedFields())) as $name) {
            foreach ([$name . 'Id', $name . 'Ids'] as $attribute) {
                if ($seed->hasAttribute($attribute)) {
                    $result[] = $attribute;
                }
            }
        }

        return $result;
    }

    public function load(EntityCollection $collection): void
    {
        $requested = AbstractService::getRequestedLinkedFields();
        if (empty($requested) || count($collection) === 0) {
            return;
        }

        // [entity id => [field name => ['entity' => linked entity name, 'multiple' => bool]]]
        $fieldsMap = [];
        $ids       = [];
        foreach ($collection as $entity) {
            $fields = $this->getFieldList($entity, $requested);
            if (empty($fields)) {
                continue;
            }

            $fieldsMap[$entity->id] = $fields;
            foreach ($fields as $name => $field) {
                foreach ((array)$entity->get($field['multiple'] ? $name . 'Ids' : $name . 'Id') as $id) {
                    if (!empty($id)) {
                        $ids[$field['entity']][$id] = $id;
                    }
                }
            }
        }

        if (empty($fieldsMap)) {
            return;
        }

        $records = $this->loadRecords($ids);

        foreach ($collection as $entity) {
            foreach ($fieldsMap[$entity->id] ?? [] as $name => $field) {
                if ($field['multiple']) {
                    $list = [];
                    foreach ((array)$entity->get($name . 'Ids') as $id) {
                        if (isset($records[$field['entity']][$id])) {
                            $list[] = $records[$field['entity']][$id];
                        }
                    }
                    $entity->setMeta('linkedRecords', $name, $list);
                } else {
                    $id = $entity->get($name . 'Id');
                    $entity->setMeta('linkedRecords', $name, !empty($id) ? ($records[$field['entity']][$id] ?? null) : null);
                }
            }
        }
    }

    /**
     * @param array<string, array<string, string>> $ids the ids of the records per entity name
     *
     * @return array<string, array<string, array>> the records per entity name and id, only the readable ones
     */
    protected function loadRecords(array $ids): array
    {
        $records = [];
        foreach ($ids as $entityName => $entityIds) {
            if (!$this->container->get('acl')->check($entityName, 'read')) {
                continue;
            }

            // the linked records are prepared as a collection, the same way as the records of a list
            $service    = $this->container->get('serviceFactory')->create($entityName);
            $collection = $this->container->get('entityManager')->getRepository($entityName)->where(['id' => array_values($entityIds)])->find();
            $service->prepareCollectionForOutput($collection);
            foreach ($collection as $entity) {
                $service->loadAdditionalFieldsForList($entity);
                $service->prepareEntityForOutput($entity);
                $records[$entityName][$entity->get('id')] = $entity->toArray();
            }
        }

        return $records;
    }

    /**
     * The link and linkMultiple fields and attributes of the record that are requested. The value * stands for the
     * link fields and the link and linkMultiple attributes: a linkMultiple field has to be listed by its name, as the
     * ids of its records are loaded with an additional query.
     *
     * @return array<string, array{entity: string, multiple: bool}>
     */
    protected function getFieldList(Entity $entity, array $requested): array
    {
        $all        = in_array('*', $requested, true);
        $entityType = $entity->getEntityName();
        $metadata   = $this->container->get('metadata');

        // the fields of the entity type are resolved once
        if (!isset($this->fieldDefs[$entityType])) {
            $this->fieldDefs[$entityType] = [];
            foreach ($metadata->get(['entityDefs', $entityType, 'fields'], []) as $name => $defs) {
                if (empty($defs['type']) || !in_array($defs['type'], ['link', 'linkMultiple'], true)) {
                    continue;
                }

                $entityName = $defs['entity'] ?? $metadata->get(['entityDefs', $entityType, 'links', $name, 'entity']);
                if (!empty($entityName)) {
                    $this->fieldDefs[$entityType][$name] = ['entity' => $entityName, 'multiple' => $defs['type'] === 'linkMultiple'];
                }
            }
        }

        $result = [];
        foreach ($this->fieldDefs[$entityType] as $name => $field) {
            if (in_array($name, $requested, true) || ($all && !$field['multiple'])) {
                $result[$name] = $field;
            }
        }

        // the attributes differ from record to record
        if ($metadata->get(['scopes', $entityType, 'hasAttribute'])) {
            foreach ($entity->entityDefs['fields'] ?? [] as $name => $defs) {
                if (
                    !empty($defs['attributeId']) && !empty($defs['entity'])
                    && in_array($defs['type'] ?? null, ['link', 'linkMultiple'], true)
                    && ($all || in_array($name, $requested, true))
                ) {
                    $result[$name] = ['entity' => $defs['entity'], 'multiple' => $defs['type'] === 'linkMultiple'];
                }
            }
        }

        return $result;
    }
}
