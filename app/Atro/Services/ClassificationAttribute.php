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
use Atro\Core\Templates\Services\Base;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\Entity as OrmEntity;
use Espo\ORM\EntityCollection;

class ClassificationAttribute extends Base
{
    protected $mandatorySelectAttributeList
        = [
            'classificationId',
            'attributeId',
            'isRequired',
            'data'
        ];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (!empty($entity->get('data')->default)) {
            $entity->set($entity->get('data')->default);
        }

        $attribute = $this->getEntityManager()->getRepository('Attribute')->get($entity->get('attributeId'));

        if (!empty($attribute)) {
            // the settings of the attribute type are stored in its subtype record
            $settings = $attribute->getSubtype();

            $entity->set('attributeData', $this->getEntityManager()->getRepository('Attribute')->getAttributeData($attribute));
            $entity->set('attributeEntityId', $attribute->get('entityId'));
            $entity->set('attributeType', $attribute->get('type'));
            $entity->set('attributeGroupId', $attribute->get('attributeGroupId'));
            $entity->set('attributeGroupName', $attribute->get('attributeGroupName'));
            $entity->set('attributeNotNull', $settings?->get('notNull'));
            $entity->set('attributeIsMultilang', $settings?->get('isMultilang'));
            $entity->set('sortOrder', $attribute->get('sortOrder'));

            if (!empty($this->getConfig()->get('isMultilangActive'))) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale)));
                    $entity->set('attributeName' . $preparedLocale, $attribute->get('name' . $preparedLocale));
                }
            }

            if (!empty($settings?->get('measureId'))) {
                $entity->set('attributeMeasureId', $settings?->get('measureId'));
                $this->prepareUnitFieldValue($entity, 'value', [
                    'measureId' => $settings?->get('measureId'),
                    'type'      => $attribute->get('type'),
                    'mainField' => 'value'
                ]);
            }

            if ($attribute->get('type') === 'link' && !empty($settings?->get('entityType'))) {
                $entity->set('attributeEntityType', $settings?->get('entityType'));
                $foreign = $this->getEntityManager()->getEntity($settings?->get('entityType'), $entity->get('valueId'));
                if (!empty($foreign)) {
                    $entity->set('valueName', $foreign->get($settings?->get('entityField') ?? 'name'));
                }
                if (!empty($settings?->get('entityField'))) {
                    $entity->set('attributeEntityField', $settings?->get('entityField'));
                }
            }

            if ($attribute->get('type') === 'linkMultiple' && !empty($settings?->get('entityType'))) {
                $entity->set('attributeEntityType', $settings?->get('entityType'));

                if (!empty($entity->get('valueIds'))) {
                    $values = $this->getEntityManager()->getRepository($settings?->get('entityType'))
                        ->where(['id' => $entity->get('valueIds')])
                        ->find();

                    if (isset($values[0])) {
                        $entity->set('valueNames', array_column($values->toArray(), 'name', 'id'));
                    }
                }
            }

            if ($attribute->get('type') === 'file') {
                $file = $this->getEntityManager()->getEntity('File', $entity->get('valueId'));
                if (!empty($file)) {
                    $entity->set("valueName", $file->get('name'));
                    $entity->set("valuePathsData", $file->getPathsData());
                }
                if (!empty($settings?->get('fileTypeId'))) {
                    $entity->set('attributeFileTypeId', $settings?->get('fileTypeId'));
                }
            }
        }
    }

    public function createEntity(\stdClass $attachment): string
    {
        if (!property_exists($attachment, 'attributeId')) {
            throw new BadRequest("'attributeId' is required.");
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $this->prepareDefaultValues($attachment);
            $id = parent::createEntity($attachment);
            $this->createPseudoTransactionCreateJobs($attachment);

            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $id;
    }

    public function putAclMetaForLink(OrmEntity $entityFrom, string $link, OrmEntity $entity): void
    {
        if ($entityFrom->getEntityName() !== 'Classification' || $link !== 'classificationAttributes') {
            parent::putAclMetaForLink($entityFrom, $link, $entity);
            return;
        }

        $this->putAclMeta($entity);

        $entity->setAvailableAction('unlinkRelatedAttribute',  $this->getUser()->isAdmin() ?? $this->getAcl()->check($entity, 'delete'));
        $entity->setAvailableAction('cascadeUnlinkRelatedAttribute', $this->getUser()->isAdmin() ?? $this->getAcl()->check($entity, 'delete'));
    }

    protected function createPseudoTransactionCreateJobs(\stdClass $data, string $parentTransactionId = null): void
    {
        if (!property_exists($data, 'classificationId') || !property_exists($data, 'attributeId')) {
            return;
        }

        $classification = $this->getEntityManager()->getEntity('Classification', $data->classificationId);
        if (empty($classification)) {
            return;
        }

        $entityName = $classification->get('entityId');

        foreach ($this->getRepository()->getClassificationRelatedRecords($classification) as $id) {
            $parentId = $this
                ->getPseudoTransactionManager()
                ->pushCustomJob('Attribute', 'createAttributeValue', [
                    'entityName' => $entityName,
                    'entityId'   => $id,
                    'data'       => $data
                ]);

            $this
                ->getPseudoTransactionManager()
                ->pushUpdateEntityJob($entityName, $id, ['modifiedAt' => date('Y-m-d H:i:s')], $parentId);
        }
    }

    protected function prepareDefaultValues(\stdClass $data): void
    {
        if (property_exists($data, 'attributeId') && !empty($data->attributeId)) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $data->attributeId);
        }

        if (empty($attribute)) {
            return;
        }

        // the settings of the attribute type are stored in its subtype record
        $settings = $attribute->getSubtype();
        foreach (['maxLength', 'isRequired', 'countBytesInsteadOfCharacters', 'min', 'max'] as $field) {
            if (!property_exists($data, $field)) {
                $data->$field = $field === 'isRequired' ? $attribute->get($field) : $settings?->get($field);
            }
        }
    }

    public function deleteEntityWithThemAttributeValues($id): bool
    {
        /**
         * ID can be an array with one item. It is needs to execute this method from custom pseudo transaction in advanced classification module
         */
        if (is_array($id)) {
            $id = array_shift($id);
        }

        $classificationData = $this->getRepository()->getClassificationDataForClassificationAttributeId($id);

        $result = parent::deleteEntity($id);

        if ($result && !empty($classificationData)) {
            $this
                ->getRepository()
                ->deleteAttributeValuesByClassificationAttribute(
                    $classificationData['entity_id'],
                    $classificationData['attribute_id'],
                    $classificationData['classification_id']
                );
        }

        return $result;
    }

    public function sortCollection(EntityCollection $collection): void
    {
        $attributes = [];
        foreach ($this->getEntityManager()->getRepository('Attribute')->where(['id' => array_column($collection->toArray(), 'attributeId')])->find() as $attribute) {
            $attributes[$attribute->get('id')] = $attribute;
        }

        $records = [];
        foreach ($collection as $k => $entity) {
            $row = [
                'entity' => $entity,
            ];

            $attribute = $attributes[$entity->get('attributeId')] ?? null;
            if (empty($attribute)) {
                continue;
            }

            $row['sortOrder'] = empty($attribute->get('sortOrder')) ? 0 : (int)$attribute->get('sortOrder');

            $records[$k] = $row;
        }

        array_multisort(
            array_column($records, 'sortOrder'), SORT_ASC,
            $records
        );

        foreach ($records as $k => $record) {
            $collection->offsetSet($k, $record['entity']);
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        $this->sortCollection($collection);

        parent::prepareCollectionForOutput($collection);
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
