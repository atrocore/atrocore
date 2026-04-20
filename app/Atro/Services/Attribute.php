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

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\ORM\Repositories\RDB;
use Atro\Core\Templates\Services\Base;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class Attribute extends Base
{
    protected $mandatorySelectAttributeList = ['sortOrder', 'attributeGroupSortOrder', 'extensibleEnumId', 'data', 'measureId', 'defaultUnit', 'entityId', 'outputType'];

    public function getAttributesDefs(string $entityName, array $attributesIds): array
    {
        $entity = $this->getEntityManager()->getRepository($entityName)->get();
        if (!$entity) {
            return [];
        }

        $attributes = $this->getRepository()->getAttributesByIds($attributesIds);

        /** @var AttributeFieldConverter $converter */
        $converter = $this->getInjection(AttributeFieldConverter::class);

        $attributesDefs = [];
        foreach ($attributes as $row) {
            $converter->getFieldType($row['type'])->convert($entity, $row, $attributesDefs);
        }

        return $attributesDefs;
    }

    public function createAttributeValuesFromClassification(string $classificationId, string $entityName, string $entityId): void
    {
        $cas = $this->getEntityManager()->getRepository('ClassificationAttribute')
            ->where([
                'classificationId' => $classificationId
            ])
            ->find();

        if (empty($cas[0])) {
            return;
        }

        $res = false;

        // check composite attributes
        $compositeIds = $this->getRepository()->getCompositeAttributeIdsFromClassification($classificationId);

        if (!empty($compositeIds)) {
            $this->addAttributeValue($entityName, $entityId, null, $compositeIds);
            $res = true;
        }

        foreach ($cas as $ca) {
            if (in_array($ca->get('attributeId'), $compositeIds)) {
                continue;
            }

            $data = $ca->get('data')?->default ?? new \stdClass();
            $data = json_decode(json_encode($data), true);
            $data['attributeId'] = $ca->get('attributeId');

            $created = $this->createAttributeValue([
                'entityName'      => $entityName,
                'entityId'        => $entityId,
                'data'            => $data,
                'skipAfterCreate' => true
            ]);

            if (empty($res) && !empty($created)) {
                $res = true;
            }
        }

        if ($res) {
            $this->afterCreateDeleteAttributeValue($entityName, $entityId);
        }
    }

    public function createAttributeValue(array $pseudoTransactionData): bool
    {
        $entityName = $pseudoTransactionData['entityName'] ?? null;
        $entityId = $pseudoTransactionData['entityId'] ?? null;
        $data = $pseudoTransactionData['data'] ?? [];

        if (empty($entityName) || empty($entityId) || empty($data)) {
            return false;
        }

        if (!$this->getAcl()->check($entityName, 'createAttributeValue')) {
            return false;
        }

        $entity = $this->getEntityManager()->getRepository($entityName)->get($entityId);
        if (empty($entity)) {
            return false;
        }

        /** @var \Atro\Repositories\Attribute $attributeRepo */
        $attributeRepo = $this->getRepository();

        $attributes = $attributeRepo->getAttributesByIds([$data['attributeId']]);
        if (empty($attributes[0])) {
            return false;
        }

        $row = $attributes[0];

        if ($attributeRepo->hasAttributeValue($entityName, $entityId, $row['id'])) {
            return false;
        }

        /** @var AttributeFieldConverter $converter */
        $converter = $this->getInjection(AttributeFieldConverter::class);

        $attributesDefs = [];
        $converter->getFieldType($row['type'])->convert($entity, $row, $attributesDefs);
        $attributeFieldName = AttributeFieldConverter::prepareFieldName($row);

        // set null value
        foreach ($entity->fields ?? [] as $field => $fieldDefs) {
            $valueName = str_replace($attributeFieldName, 'value', $field);
            if (!empty($fieldDefs['attributeId']) && $fieldDefs['attributeId'] === $row['id'] && !empty($fieldDefs['column']) && !array_key_exists($valueName, $data)) {
                $data[$valueName] = null;
            }
        }

        // set default value if it needs
        foreach (['value', 'valueFrom', 'valueTo', 'valueUnitId', 'valueId', 'valueIds'] as $key) {
            if (array_key_exists($key, $data)) {
                $fieldName = str_replace('value', $attributeFieldName, $key);
                $attributeRepo->upsertAttributeValue($entity, $fieldName, $data[$key], false);
            }
        }

        if (empty($pseudoTransactionData['skipAfterCreate'])) {
            $this->afterCreateDeleteAttributeValue($entityName, $entityId);
        }

        return true;
    }

    public function addAttributeValue(string $entityName, string $entityId, ?array $where, ?array $ids): bool
    {
        if (!$this->getAcl()->check($entityName, 'createAttributeValue')) {
            throw new Forbidden();
        }

        if ($where !== null) {
            $selectParams = $this
                ->getSelectManager()
                ->getSelectParams(['where' => json_decode(json_encode($where), true)], true);
            $attributes = $this->getRepository()->find($selectParams);
        } elseif ($ids !== null) {
            $attributes = $this->getRepository()->where(['id' => $ids])->find();
        }

        if (empty($attributes)) {
            return false;
        }

        if (!$this->getUser()->isAdmin()) {
            $forbiddenFieldsList = $this->getAcl()->getScopeForbiddenAttributeList($entityName, 'edit');
            if (!empty($forbiddenFieldsList)) {
                $attributesDefs = $this->getAttributesDefs($entityName, array_column($attributes->toArray(), 'id'));
                foreach ($attributesDefs as $field => $defs) {
                    if (in_array($field, $forbiddenFieldsList)) {
                        throw new Forbidden();
                    }
                }
            }
        }

        foreach ($attributes as $attribute) {
            try {
                $this->getRepository()->addAttributeValue($entityName, $entityId, $attribute->get('id'));
            } catch (UniqueConstraintViolationException $e) {
                // ignore
            }
        }

        $this->afterCreateDeleteAttributeValue($entityName, $entityId);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getEntity($id = null)
    {
        $id = $this
            ->dispatchEvent('beforeGetEntity', new Event(['id' => $id]))
            ->getArgument('id');

        $entity = $this->getRepository()->get($id);

        if (!empty($entity) && $this->getConfig()->get('isMultilangActive', false) && $entity->get('isMultilang')) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $camelCaseLocale = Util::toCamelCase(strtolower($locale), '_', true);
                if (!empty($ownerUserId = $entity->get("ownerUser{$camelCaseLocale}Id"))) {
                    $ownerUser = $this->getEntityManager()->getEntity('User', $ownerUserId);
                    $entity->set("ownerUser{$camelCaseLocale}Name", $ownerUser->get('name'));
                } else {
                    $entity->set("ownerUser{$camelCaseLocale}Name", null);
                }

                if (!empty($assignedUserId = $entity->get("assignedUser{$camelCaseLocale}Id"))) {
                    $assignedUser = $this->getEntityManager()->getEntity('User', $assignedUserId);
                    $entity->set("assignedUser{$camelCaseLocale}Name", $assignedUser->get('name'));
                } else {
                    $entity->set("assignedUser{$camelCaseLocale}Name", null);
                }
            }
        }

        if (!empty($entity) && !empty($id)) {
            $this->loadAdditionalFields($entity);

            if (!$this->getAcl()->check($entity, 'read')) {
                throw new Forbidden();
            }
        }
        if (!empty($entity)) {
            $this->prepareEntityForOutput($entity);
        }

        return $this
            ->dispatchEvent('afterGetEntity', new Event(['id' => $id, 'entity' => $entity]))
            ->getArgument('entity');
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $dropdownTypes = $this->getMetadata()->get(['app', 'attributeDropdownTypes'], []);
        if (in_array($entity->get('type'), array_keys($dropdownTypes)) && $entity->get('dropdown') === null) {
            $entity->set('dropdown', false);
        }

        if (!empty($entity->get('htmlSanitizerId'))) {
            $htmlSanitizer = $this->getEntityManager()->getRepository('HtmlSanitizer')->get($entity->get('htmlSanitizerId'));
            if (!empty($htmlSanitizer)) {
                $entity->set('htmlSanitizerName', $htmlSanitizer->get('name'));
            }
        }

        if ($entity->get('fullWidth') === null) {
            $entity->set('fullWidth', in_array($entity->get('type'), ['wysiwyg', 'markdown', 'text', 'composite']));
        }

        if($entity->get('type') === 'bool') {
            $entity->set('allowNullForBool', empty($entity->get('notNull')));
        }
    }

    public function updateEntity(string $id, \stdClass $data): bool
    {
        foreach (['attributeGroupSortOrder', 'sortOrder'] as $field) {
            if (property_exists($data, $field) && property_exists($data, '_sortedIds')) {
                $this->getRepository()->updateSortOrder($data->_sortedIds, $field);
                return true;
            }
        }

        return parent::updateEntity($id, $data);
    }

    protected function init()
    {
        parent::init();

        // add dependencies
        $this->addDependency('language');
        $this->addDependency(AttributeFieldConverter::class);
    }

    /**
     * Get multilang fields
     *
     * @return array
     */
    protected function getMultilangFields(): array
    {
        // get config
        $config = $this->getConfig()->get('modules');

        return (!empty($config['multilangFields'])) ? array_keys($config['multilangFields']) : [];
    }

    protected function checkFieldsWithPattern(Entity $entity): void
    {
        $attributeList = array_keys($this->getInjection('metadata')->get(['attributes']));
        if (!in_array($entity->get('type'), $attributeList)) {
            throw new Forbidden(str_replace('{type}', $entity->get('type'),
                $this->getInjection('language')->translate('invalidType', 'exceptions', 'Attribute')));
        }

        parent::checkFieldsWithPattern($entity);
    }

    protected function afterCreateDeleteAttributeValue(string $entityName, string $entityId, bool $fromClassificationAttribute = false): void
    {
        $event = new Event(['entityName' => $entityName, 'entityId' => $entityId, 'fromClassificationAttribute' => $fromClassificationAttribute]);;
        $this->dispatchEvent('afterCreateDeleteAttributeValue', $event);
    }
}
