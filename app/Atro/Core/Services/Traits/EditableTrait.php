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

namespace Atro\Core\Services\Traits;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Conflict;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\RegexUtil;
use Atro\Core\Utils\UniqueIndexDescriber;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\Core\Utils\Json;
use Atro\Core\Services\AbstractRecordService;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

/**
 * Creating and updating the records: the input, its validation, storing and the duplication of the records.
 */
trait EditableTrait
{
    protected $readOnlyAttributeList = [];

    protected function storeEntity(Entity $entity)
    {
        // the repository resets the "new" flag while saving, so it has to be remembered here
        $isNew = $entity->isNew();

        $this->proceedOneToOneRelationshipBeforeStoreEntity($entity);

        $this->getUniqueIndexDescriber()->prepare($entity->getEntityName());

        try {
            $result = $this->getRepository()->save($entity, $this->getDefaultRepositoryOptions());
        } catch (UniqueConstraintViolationException $e) {
            if (!empty($this->getMemoryStorage()->get('importJobId'))) {
                throw new NotUnique($e->getMessage());
            }

            throw new NotUnique($this->getUniqueIndexDescriber()->buildMessage($entity, $e->getMessage()));
        }

        if ($isNew) {
            $this->proceedOneToOneRelationshipAfterStoreEntity($entity);
        }

        return $result;
    }

    /**
     * Handles the processing of one-to-one relationships before storing the given entity.
     *
     * This method ensures that the appropriate updates are made to related one-to-one entities
     * by leveraging the metadata definitions. Depending on the existence of the related one-to-one
     * entity, it updates or clears the relationship for correct data consistency and integrity.
     *
     * @param Entity $entity
     *
     * @return void
     */
    protected function proceedOneToOneRelationshipBeforeStoreEntity(Entity $entity): void
    {
        $attachment = $entity->_originalInput ?? null;
        if (empty($attachment)) {
            return;
        }

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links'], []) as $link => $linkData) {
            if (!property_exists($attachment, "{$link}Id")) {
                continue;
            }

            if (($linkData['relationType'] ?? null) === 'oneToOne') {
                /** @var AbstractRecordService $service */
                $service = $this->getServiceFactory()->create($linkData['entity']);

                if (empty($attachment->{"{$link}Id"})) {
                    $fetchedId = $entity->getFetched("{$link}Id");
                    if (empty($fetchedId)) {
                        // nothing was related before, so there is nothing to unrelate
                        continue;
                    }

                    $data = new \stdClass();
                    $data->{"{$linkData['foreign']}Id"} = null;
                    $data->_ignoreProceedOneToOneRelationship = true;

                    try {
                        $service->updateEntity($fetchedId, $data);
                    } catch (NotModified) {
                    } catch (Forbidden) {
                        $this->throwOneToOneForbidden('oneToOneNoEditAccessToPreviousRelatedRecord', $link, $linkData);
                    }
                } else {
                    $relEntity = $this->getRepository()
                        ->select(['id'])
                        ->where([
                            'id!='      => $entity->id,
                            "{$link}Id" => $attachment->{"{$link}Id"}
                        ])
                        ->findOne();

                    if (!empty($relEntity)) {
                        $data = new \stdClass();
                        $data->{"{$link}Id"} = null;
                        $data->_ignoreProceedOneToOneRelationship = true;

                        try {
                            $this->updateEntity($relEntity->id, $data);
                        } catch (Forbidden) {
                            $this->throwOneToOneForbidden('oneToOneNoEditAccessToOccupyingRecord', $link, $linkData);
                        }
                    }

                    if (empty($entity->get('id'))) {
                        // on create the id is generated while saving, so relating is done in
                        // proceedOneToOneRelationshipAfterStoreEntity
                        continue;
                    }

                    $data = new \stdClass();
                    $data->{"{$linkData['foreign']}Id"} = $entity->get('id');
                    $data->_ignoreProceedOneToOneRelationship = true;

                    try {
                        $service->updateEntity($attachment->{"{$link}Id"}, $data);
                    } catch (NotModified) {
                    } catch (Forbidden) {
                        $this->throwOneToOneForbidden('oneToOneNoEditAccessToRelatedRecord', $link, $linkData);
                    }
                }

                continue;
            }

            // proceed storing OneToOne from virtual field
            if (empty($attachment->_ignoreProceedOneToOneRelationship) && !empty($this->getOneToOneRelationData($link))) {
                /** @var AbstractRecordService $service */
                $service = $this->getServiceFactory()->create($linkData['entity']);

                if (empty($attachment->{"{$link}Id"})) {
                    $relEntity = $this->getEntityManager()->getRepository($linkData['entity'])
                        ->select(['id'])
                        ->where([
                            "{$linkData['foreign']}Id" => $entity->get('id')
                        ])
                        ->findOne();

                    if (!empty($relEntity)) {
                        $data = new \stdClass();
                        $data->{"{$linkData['foreign']}Id"} = null;
                        try {
                            $service->updateEntity($relEntity->id, $data);
                        } catch (NotModified) {
                        } catch (Forbidden) {
                            $this->throwOneToOneForbidden('oneToOneNoEditAccessToPreviousRelatedRecord', $link, $linkData);
                        }
                    }
                } elseif (!empty($entity->get('id'))) {
                    // on create the id is generated while saving, so relating is done in
                    // proceedOneToOneRelationshipAfterStoreEntity
                    $data = new \stdClass();
                    $data->{"{$linkData['foreign']}Id"} = $entity->get('id');
                    try {
                        $service->updateEntity($attachment->{"{$link}Id"}, $data);
                    } catch (NotModified) {
                    } catch (Forbidden) {
                        $this->throwOneToOneForbidden('oneToOneNoEditAccessToRelatedRecord', $link, $linkData);
                    }
                }
            }
        }
    }

    /**
     * Handles the processing of one-to-one relationships after storing a new entity. The id of a new entity is
     * generated while saving, therefore such relationships can only be stored once the entity has been saved.
     * Has to be called for new entities only.
     *
     * @param Entity $entity The entity that has been stored and is potentially involved in one-to-one relationships.
     *
     * @return void
     */
    protected function proceedOneToOneRelationshipAfterStoreEntity(Entity $entity): void
    {
        $attachment = $entity->_originalInput ?? null;
        if (empty($attachment)) {
            return;
        }

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links'], []) as $link => $linkData) {
            if (empty($attachment->{"{$link}Id"})) {
                continue;
            }

            $data = new \stdClass();
            $data->{"{$linkData['foreign']}Id"} = $entity->get('id');

            if (($linkData['relationType'] ?? null) === 'oneToOne') {
                $data->_ignoreProceedOneToOneRelationship = true;
            } elseif (empty($this->getOneToOneRelationData($link))) {
                // neither an one-to-one relation nor a virtual field of it
                continue;
            }

            /** @var AbstractRecordService $service */
            $service = $this->getServiceFactory()->create($linkData['entity']);

            try {
                $service->updateEntity($attachment->{"{$link}Id"}, $data);
            } catch (NotModified) {
            } catch (Forbidden) {
                $this->throwOneToOneForbidden('oneToOneNoEditAccessToRelatedRecord', $link, $linkData);
            }
        }
    }

    protected function getOneToOneRelationData(string $link): ?array
    {
        $linkData = $this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link], []);

        if (
            ($linkData['type'] ?? null) === 'hasOne'
            && !empty($linkData['entity'])
            && !empty($linkData['foreign'])
            && $this->getMetadata()->get(['entityDefs', $linkData['entity'], 'links', $linkData['foreign'], 'type']) === 'belongsTo'
        ) {
            return $linkData;
        }

        return null;
    }

    /**
     * A one-to-one relation is physically stored on the related record, not on the current one. Because of that,
     * relating or unrelating it here always means an update of the record(s) on the other side of the relation.
     * If the user has no edit access to them, the current record cannot be saved either - what looks like a
     * permission problem on the current record is in fact a permission problem on the related one, so the message
     * has to name it explicitly.
     *
     * @param string $messageKey
     * @param string $link
     * @param array  $linkData
     *
     * @return void
     * @throws Forbidden
     */
    protected function throwOneToOneForbidden(string $messageKey, string $link, array $linkData): void
    {
        $language = $this->getInjection('language');

        throw new Forbidden(
            sprintf(
                $language->translate($messageKey, 'exceptions', 'Global'),
                $language->translate($linkData['entity'], 'scopeNames', 'Global'),
                $language->translate($link, 'fields', $this->entityName),
                $language->translate($this->entityName, 'scopeNames', 'Global')
            )
        );
    }

    protected function checkProtectedFields(Entity $entity, \stdClass $data): void
    {
        foreach ($entity->getAttributes() as $field => $ormDefs) {
            if (
                !property_exists($data, $field)
                || !empty($ormDefs['isLinkEntity'])
                || !empty($ormDefs['isLinkEntityName'])
                || !empty($ormDefs['isLinkMultipleCollection'])
                || !empty($ormDefs['isLinkMultipleNameMap'])
            ) {
                continue;
            }

            $fieldName = $ormDefs['originalName'] ?? $field;

            $protected = $entity->entityDefs['fields'][$fieldName]['protected']
                ?? $this->getMetadata()->get("entityDefs.{$entity->getEntityType()}.fields.$fieldName.protected");

            if ($protected || $this->isProtectedFieldViaConditions($fieldName, $entity)) {
                unset($data->$field);
            }
        }
    }

    protected function checkRequiredFields(Entity $entity, \stdClass $data): bool
    {
        if ($entity->isSkippedValidation('requiredField')) {
            return true;
        }

        /** @var Language $language */
        $language = $this->getInjection('language');

        foreach ($this->getRequiredFields($entity, $data) as $field) {
            if ($this->isNullField($entity, $field)) {
                $fieldName = htmlentities($language->translate($field, 'fields', $entity->getEntityType()));
                if (isset($entity->entityDefs['fields'][$field]['label'])) {
                    $fieldName = $entity->entityDefs['fields'][$field]['label'];
                } elseif (!empty($attributeName = $entity->getAttributeParam($field, 'name')) && isset($entity->entityDefs['fields'][$attributeName]['label'])) {
                    $fieldName = $entity->entityDefs['fields'][$attributeName]['label'];
                }
                $message = sprintf($language->translate('fieldIsRequired', 'exceptions'), $fieldName);
                throw (new BadRequest($message))->setDataItem('field', $field);
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     *
     * @throws BadRequest
     * @throws Error
     */
    protected function checkFieldsWithPattern(Entity $entity): void
    {
        foreach ($entity->entityDefs['fields'] ?? [] as $field => $defs) {
            if (!empty($defs['pattern']) && !empty($entity->get($field))) {
                $this->validateFieldWithPattern($entity, $field, $defs);
            }
        }
    }

    protected function validateDisabledOptions(Entity $entity): void
    {
        /** @var Language $language */
        $language = $this->getInjection('language');

        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityName(), 'fields']) as $field => $defs) {
            if (empty($defs['type']) || !in_array($defs['type'], ['enum', 'multiEnum', 'link', 'linkMultiple'])) {
                continue;
            }

            if (in_array($defs['type'], ['link'])) {
                $value = $entity->get($field . 'Id');
            } elseif (in_array($defs['type'], ['linkMultiple'])) {
                $value = $entity->get($field . 'Ids') ?? [];
            } else {
                $value = $entity->get($field);
            }

            if (empty($value)) {
                continue;
            }

            $rules = $entity->entityDefs['fields'][$field]['conditionalProperties']['disableOptions'] ??
                $this->getMetadata()->get("entityDefs.{$entity->getEntityName()}.fields.{$field}.conditionalProperties.disableOptions") ?? [];

            $disabledOptions = [];
            foreach ($rules as $rule) {
                if ($this->getConditionChecker()->check($entity, $rule['conditionGroup'])) {
                    $disabledOptions = array_merge($disabledOptions, $rule['options']);
                }
            }

            if (empty($disabledOptions)) {
                continue;
            }

            if (!is_array($value)) {
                $value = [$value];
            }

            foreach ($value as $item) {
                if (in_array($item, $disabledOptions)) {
                    $label = $language->translate($field, 'fields', $this->entityType);
                    throw new BadRequest(
                        sprintf($language->translate('optionIsDisabled', 'exceptions', 'Global'), $item, $label)
                    );
                }
            }
        }
    }

    /**
     * @param Entity $entity
     * @param string $field
     * @param array  $defs
     *
     * @throws BadRequest
     * @throws Error
     */
    protected function validateFieldWithPattern(Entity $entity, string $field, array $defs): void
    {
        if (empty($defs['pattern'])) {
            return;
        }

        $pattern = $defs['pattern'];
        if (!preg_match(RegexUtil::toPhpPattern($pattern), $entity->get($field))) {
            $message = $this->getInjection('language')->translate('dontMatchToPattern', 'exceptions', $entity->getEntityType());
            $message = str_replace('{field}', $defs['label'] ?? $field, $message);
            $message = str_replace('{pattern}', $pattern, $message);

            throw new BadRequest($message);
        }
    }

    /**
     * @param Entity    $entity
     * @param \stdClass $data
     *
     * @return array
     */
    protected function getRequiredFields(Entity $entity, \stdClass $data): array
    {
        $event = $this->dispatchEvent('beforeGetRequiredFields', new Event(['entity' => $entity, 'data' => $data]));

        $res = $this->collectRequiredFields($entity, $event->getArgument('data'));

        return $this
            ->dispatchEvent('afterGetRequiredFields', new Event(['entity' => $entity, 'data' => $data, 'result' => $res]))
            ->getArgument('result');
    }

    protected function collectRequiredFields(Entity $entity, \stdClass $data): array
    {
        $fields = [];

        foreach ($entity->getAttributes() as $field => $fieldData) {
            if (
                !empty($fieldData['isLinkEntity'])
                || !empty($fieldData['isLinkEntityName'])
                || !empty($fieldData['isLinkMultipleCollection'])
                || !empty($fieldData['isLinkMultipleNameMap'])
                || !empty($fieldData['originalName']) && in_array($field, [$fieldData['originalName'] . 'Name', $fieldData['originalName'] . 'Names', $fieldData['originalName'] . 'PathsData'])
            ) {
                continue;
            }

            $fieldName = $fieldData['originalName'] ?? $fieldData['name'] ?? $field;

            $required = $entity->entityDefs['fields'][$fieldName]['required']
                ?? $this->getMetadata()->get("entityDefs.{$entity->getEntityName()}.fields.$fieldName.required");

            if (!empty($required) || $this->isRequiredFieldViaConditions($fieldName, $entity)) {
                if (!empty($fieldData['attributeId']) && !empty($entity->_originalInput ?? null)) {
                    // skip the attribute if it is being added to the record (For only adding attributes)
                    if (property_exists($entity->_originalInput, '__attributes') &&
                        count(get_object_vars($entity->_originalInput)) == 1 &&
                        !$entity->hasAttributeValue($fieldName)) {
                        continue;
                    }

                    // skip the attribute if it is being removed from the record
                    if (property_exists($entity->_originalInput, '__attributesToRemove') &&
                        in_array($fieldData['attributeId'], $entity->_originalInput->__attributesToRemove ?? [])) {
                        continue;
                    }

                    // skip the attribute if its classification is being unlinked
                    $classificationId = $entity->entityDefs['fields'][$fieldName]['classificationId'] ?? null;
                    if (!empty($classificationId) &&
                        property_exists($entity->_originalInput, 'classificationsIds') &&
                        !in_array($classificationId, $entity->_originalInput->classificationsIds ?? [])) {
                        continue;
                    }
                }

                $fields[] = $field;
            }
        }

        return $fields;
    }

    protected function filterInputAttribute($attribute, $value)
    {
        $methodName = 'filterInputAttribute' . ucfirst($attribute);
        if (method_exists($this, $methodName)) {
            $value = $this->$methodName($value);
        }
        return $value;
    }

    protected function prepareInputByFieldTypes(Entity $entity, \stdClass $input): void
    {
        foreach ($input as $field => $value) {
            $fieldDefs = $entity->entityDefs['fields'][$field] ?? $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields', $field]);
            if (empty($fieldDefs['type'])) {
                continue;
            }

            if ($fieldDefs['type'] === 'float' && $value !== null) {
                if (array_key_exists('amountOfDigitsAfterComma', $fieldDefs) && $fieldDefs['amountOfDigitsAfterComma'] > 0) {
                    $input->$field = round($value, $fieldDefs['amountOfDigitsAfterComma']);
                }
            }
        }
    }

    protected function filterInput($data, ?Entity $entity = null)
    {
        if (!is_object($data)) {
            return;
        }

        if ($entity === null && property_exists($data, '_prev')) {
            unset($data->_prev);
        }

        foreach ($this->readOnlyAttributeList as $attribute) {
            unset($data->$attribute);
        }

        foreach ($data as $key => $value) {
            $data->$key = $this->filterInputAttribute($key, $data->$key);
        }

        foreach ($this->getAcl()->getScopeForbiddenAttributeList($this->entityType, 'edit') as $attribute) {
            unset($data->$attribute);
            if (!empty($attributeId = $entity->fields[$attribute]['attributeId'] ?? null)) {
                // if this attribute is forbidden for editing, remove it from the removal list too
                if (!empty($entity->_originalInput->__attributesToRemove)) {
                    $entity->_originalInput->__attributesToRemove = array_values(
                        array_filter($entity->_originalInput->__attributesToRemove, fn($id) => $id !== $attributeId)
                    );
                }

                // if we try to add forbidden attribute, we should remove it from entityDefs
                foreach ($entity->entityDefs['fields'] as $field => $fieldDefs) {
                    if (!empty($fieldDefs['attributeId']) && $fieldDefs['attributeId'] === $attributeId && empty($fieldDefs['attributeValueId'])) {
                        unset($entity->entityDefs['fields'][$field]);

                        foreach ($entity->fields as $field => $fieldData) {
                            if (!empty($fieldData['attributeId']) && $fieldData['attributeId'] === $attributeId) {
                                unset($entity->fields[$field]);
                            }
                        }
                    }
                }
            }
        }

        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            foreach ($data as $field => $value) {
                $fieldDefs = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $field]);
                if (empty($fieldDefs['type']) || empty($fieldDefs['multilangField'])) {
                    continue 1;
                }

                switch ($fieldDefs['type']) {
                    case 'enum':
                        if (!property_exists($data, $fieldDefs['multilangField'])) {
                            $key                                  = array_search($value, $fieldDefs['options']);
                            $data->{$fieldDefs['multilangField']} = $key === false ? null : $fieldDefs['optionsOriginal'][$key];
                        }
                        unset($data->$field);
                        break;
                    case 'multiEnum':
                        if (!property_exists($data, $fieldDefs['multilangField'])) {
                            $keys = [];
                            if (!empty($value)) {
                                foreach ($value as $item) {
                                    $keys[] = array_search($item, $fieldDefs['options']);
                                }
                            }
                            $values = [];
                            foreach ($keys as $key) {
                                $values[] = $fieldDefs['optionsOriginal'][$key];
                            }
                            $data->{$fieldDefs['multilangField']} = $values;
                        }
                        unset($data->$field);
                        break;
                }
            }
        }

        // the subtype of a record is set by the subtype on creation and cannot be changed
        if (!empty($this->getMetadata()->get(['scopes', $this->entityType, 'hasSubtypes']))) {
            unset($data->type);
        }

        // the read-only fields of the record are not changed
        if (!empty($entity)) {
            foreach ($this->getReadOnlyFields($entity) as $field) {
                unset($data->$field, $data->{$field . 'Id'}, $data->{$field . 'Ids'});
            }
        }
    }

    protected function modifyEnumValue(?string $value, string $field, bool $validate = true): ?string
    {
        if ($value === null) {
            return null;
        }

        $fieldLabel = $this->getInjection('language')->translate($field, 'fields', $this->entityType);
        $fieldDefs  = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $field]);

        if (!isset($fieldDefs['options']) || !empty($fieldDefs['relationVirtualField'])) {
            return $value;
        }

        if (!in_array($value, $fieldDefs['options'])) {
            if (!$validate) {
                return $value;
            }
            throw new BadRequest(sprintf($this->getInjection('language')->translate('noSuchOptions', 'exceptions', 'Global'), $value, $fieldLabel));
        }

        return $value;
    }

    protected function modifyMultiEnumValue(?array $values, string $field, bool $validate = true): array
    {
        if ($values === null) {
            return [];
        }

        $fieldLabel = $this->getInjection('language')->translate($field, 'fields', $this->entityType);
        $fieldDefs  = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $field]);

        if (!isset($fieldDefs['options']) || !empty($fieldDefs['relationVirtualField'])) {
            return $values;
        }

        foreach ($values as $v) {
            if (!in_array($v, $fieldDefs['options'])) {
                if (!$validate) {
                    continue;
                }
                throw new BadRequest(sprintf($this->getInjection('language')->translate('noSuchOptions', 'exceptions', 'Global'), $v, $fieldLabel));
            }
        }

        return $values;
    }

    protected function prepareInputForAddOnlyMode(string $id, \stdClass $data): void
    {
        if (empty($entity = $this->getEntityManager()->getEntity($this->entityType, $id))) {
            return;
        }

        foreach ($data as $field => $value) {
            if (mb_strlen($field) < 12 || mb_substr($field, -11) !== 'AddOnlyMode' || empty($value)) {
                continue;
            }

            // clearing input
            unset($data->$field);

            $fieldName      = mb_substr($field, 0, -11);
            $inputFieldName = $fieldName;

            $fieldDefs = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $fieldName]);
            if (empty($fieldDefs['type'])) {
                continue;
            }

            if ($fieldDefs['type'] === 'linkMultiple') {
                $inputFieldName .= 'Ids';
            }

            if (!property_exists($data, $inputFieldName) || empty($data->$inputFieldName)) {
                continue;
            }

            switch ($fieldDefs['type']) {
                case 'array':
                case 'multiEnum':
                    $data->$inputFieldName = array_values(array_unique(array_merge(empty($entity->get($fieldName)) ? [] : $entity->get($fieldName), $data->$inputFieldName)));
                    break;
                case 'linkMultiple':
                    if (!empty($fieldDefs['attributeId'])) {
                        $data->$inputFieldName = array_values(array_unique(array_merge($entity->get($fieldName . 'Ids') ?? [], $data->$inputFieldName)));
                    } else {
                        $collection = $entity->get($fieldName);
                        if (!empty($collection[0])) {
                            $data->$inputFieldName = array_values(array_unique(array_merge(array_column($collection->toArray(), 'id'), $data->$inputFieldName)));
                        }
                    }
                    break;
            }
        }
    }

    protected function handleInput(\stdClass $data, ?string $id = null): void
    {
        if (empty($data)) {
            return;
        }

        if (!empty($id)) {
            $this->prepareInputForAddOnlyMode($id, $data);
        }

        foreach ($data as $field => $value) {
            $fieldDefs = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $field]);
            if (empty($fieldDefs['type'])) {
                continue;
            }

            /**
             * Convert unit to unitId for backward compatibility
             */
            if (!empty($fieldDefs['unitIdField']) && !property_exists($data, $field . 'Id')) {
                $units = $this->getMeasureUnits($fieldDefs['measureId']);
                foreach ($units as $unit) {
                    if ($unit->get('name') === $value) {
                        $data->{$field . 'Id'} = $unit->get('id');
                        break;
                    }
                }
            }

            switch ($fieldDefs['type']) {
                case 'enum':
                    $data->{$field} = $this->modifyEnumValue($value, $field);
                    if (property_exists($data, '_prev') && !empty($data->_prev) && property_exists($data->_prev, $field)) {
                        $value         = $data->_prev->{$field};
                        $modifiedValue = $this->modifyEnumValue($value, $field, false);
                        if ($value !== $modifiedValue && $modifiedValue === '') {
                            unset($data->_prev->{$field});
                        } else {
                            $data->_prev->{$field} = $modifiedValue;
                        }
                    }
                    break;
                case 'multiEnum':
                    $data->{$field} = $this->modifyMultiEnumValue($value, $field);
                    if (property_exists($data, '_prev') && !empty($data->_prev) && property_exists($data->_prev, $field)) {
                        $data->_prev->{$field} = $this->modifyMultiEnumValue($data->_prev->{$field}, $field, false);
                    }
                    break;
            }
        }
    }

    public function createEntity(\stdClass $attachment): string
    {
        // the records of such an entity cannot be created at all, also not as duplicates
        if (!empty($this->getMetadata()->get(['scopes', $this->entityType, 'createDisabled']))) {
            throw new NotFound($this->getInjection('language')->translate('recordCannotBeCreated', 'exceptions'));
        }

        // each record of an entity with subtypes belongs to one of them, so it is created via the subtype
        if (!empty($this->getMetadata()->get(['scopes', $this->entityType, 'hasSubtypes']))) {
            throw new BadRequest($this->getInjection('language')->translate('recordCanBeCreatedViaSubtypeOnly', 'exceptions'));
        }

        $attachment = $this
            ->dispatchEvent('beforeCreateEntity', new Event(['attachment' => $attachment, 'service' => $this]))
            ->getArgument('attachment');

        if (!empty($this->getMetadata()->get(['scopes', $this->getEntityType(), 'disabled']))) {
            throw new Forbidden();
        }

        if (!$this->getAcl()->check($this->getEntityType(), 'create')) {
            throw new Forbidden();
        }

        $entity = $this->getRepository()->get();

        // set input data to entity property
        $entity->_originalInput = $entity->_input = $attachment;

        $attributeFieldConverter = $this->getInjection(AttributeFieldConverter::class);
        $attributeFieldConverter->validateInput($entity);
        $attributeFieldConverter->putAttributesToEntity($entity);

        $this->prepareInputByFieldTypes($entity, $attachment);
        $this->filterInput($attachment);
        $this->handleInput($attachment);

        $this->checkProtectedFields($entity, $attachment);
        $this->validateArchivedLinks($entity, $attachment);
        $this->validateEntityInput($entity, $attachment);

        unset($attachment->modifiedById);
        unset($attachment->modifiedByName);
        unset($attachment->createdById);
        unset($attachment->createdByName);

        $entity->set($attachment);

        if (!$this->getAcl()->check($entity, 'create')) {
            throw new Forbidden();
        }

        $this->prepareEntityToStore($entity, $attachment);

        $this->beforeCreateEntity($entity, $attachment);

        // Are all required fields filled ?
        $this->checkRequiredFields($entity, $attachment);

        // validate enum options
        $this->validateDisabledOptions($entity);

        // validate field with pattern
        $this->checkFieldsWithPattern($entity);

        $this->validateEntityBeforeStore($entity, $attachment);

        if ($this->storeEntity($entity)) {
            if (empty($this->getMemoryStorage()->get('importJobId'))) {
                try {
                    $this->updateRelationEntity($entity, $attachment);
                } catch (Forbidden $e) {
                    // ignore 403
                }
            }

            $entity = $entity->_freshEntity ?? $entity;

            $this->afterCreateEntity($entity, $attachment);
            $this->afterCreateProcessDuplicating($entity, $attachment);

            $this->dispatchEvent('afterCreateEntity', new Event(['attachment' => $attachment, 'entity' => $entity, 'service' => $this]));

            return $entity->get('id');
        }

        throw new Error();
    }

    public function updateEntity(string $id, \stdClass $data): bool
    {
        $event = $this
            ->dispatchEvent('beforeUpdateEntity', new Event(['id' => $id, 'data' => $data, 'entityType' => $this->getEntityType(), 'service' => $this, 'result' => null]));

        $id     = $event->getArgument('id');
        $data   = $event->getArgument('data');
        $result = $event->getArgument('result');

        if ($result !== null) {
            return $result;
        }

        if (empty($id)) {
            throw new BadRequest();
        }

        if (!empty($this->getMetadata()->get(['scopes', $this->getEntityType(), 'disabled']))) {
            throw new Forbidden();
        }

        $entity = $this->getRepository()->get($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        $entity->_originalInput = clone $data;

        $attributeFieldConverter = $this->getInjection(AttributeFieldConverter::class);
        $attributeFieldConverter->validateInput($entity);
        $attributeFieldConverter->putAttributesToEntity($entity);

        $entity->_fetchedEntity = clone $entity;

        $attributeFieldConverter->prepareInputForAttributeValuesArray($entity, $data);
        $this->prepareInputByFieldTypes($entity, $data);
        $this->filterInput($data, $entity);
        $this->handleInput($data, $id);

        // remove system fields
        foreach (['modifiedById', 'modifiedByName', 'createdById', 'createdByName', 'createdAt', 'deleted'] as $field) {
            if (property_exists($data, $field)) {
                unset($data->$field);
            }
        }

        //remove script fields, it cannot be set by the api
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'fields'], []) as $field => $defs) {
            if (!empty($defs['type']) && $defs['type'] === 'script') {
                unset($data->$field);
            }
        }

        $this->validateArchivedLinks($entity, $data);
        $this->validateEntityInput($entity, $data);

        // set input data to entity property
        $entity->_input = $data;

        $this->dispatchEvent('onEntityUpdatePrepared', new Event(['entity' => $entity]));

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $this->checkProtectedFields($entity, $data);

        // check if record has been changed by someone else
        $skipCheckForConflicts = property_exists($data, '_skipCheckForConflicts') && !empty($data->_skipCheckForConflicts);
        if (empty($this->getMemoryStorage()->get('importJobId')) && !$skipCheckForConflicts && $this->getConfig()->get('checkForConflicts', true)) {
            $conflicts = $this->getFieldsThatConflict($entity, $data);
            if (!empty($conflicts)) {
                $message = $this->getInjection('language')->translate('editedByAnotherUser', 'exceptions', 'Global');
                throw (new Conflict(sprintf($message, implode(', ', $conflicts))))->setFields($conflicts);
            }
        }

        if (empty($data->_skipIsEntityUpdated) && !$this->isEntityUpdated($entity, $data)) {
            // if original input contains forbidden fields
            foreach ($this->getAcl()->getScopeForbiddenAttributeList($this->entityType, 'edit') as $attribute) {
                if (property_exists($entity->_originalInput, $attribute)) {
                    throw new Forbidden();
                }
            }

            $notModifiedEvent = $this->dispatchEvent('resolveNotModified', new Event(['entity' => $entity, 'data' => $data, 'service' => $this, 'result' => null]));
            if (!empty($notModifiedEvent->getArgument('result'))) {
                return true;
            }

            throw new NotModified();
        }

        $entity->set($data);

        $this->prepareEntityToStore($entity, $data);

        $this->beforeUpdateEntity($entity, $data);

        // Are all required fields filled ?
        $this->checkRequiredFields($entity, $data);

        // validate enum options
        $this->validateDisabledOptions($entity);

        // validate field with pattern
        $this->checkFieldsWithPattern($entity);

        $this->validateEntityBeforeStore($entity, $data);

        if ($this->storeEntity($entity)) {
            if (empty($this->getMemoryStorage()->get('importJobId')) && $this->isRelationPanelChanges($data)) {
                $this->updateRelationEntity($entity, $data);
            }

            $entity = $entity->_freshEntity ?? $entity;

            $this->afterUpdateEntity($entity, $data);

            $this->dispatchEvent('afterUpdateEntity', new Event(['id' => $id, 'data' => $data, 'entity' => $entity, 'beforeUpdateEvent' => $event, 'service' => $this]));

            return true;
        }

        throw new Error();
    }

    protected function isRelationPanelChanges(\stdClass $data): bool
    {
        return property_exists($data, '_relationName') && property_exists($data, '_relationEntity') && property_exists($data, '_relationEntityId');
    }

    protected function updateRelationEntity(Entity $entity, \stdClass $input): void
    {
        if (
            !property_exists($input, '_relationEntityId')
            || !property_exists($input, '_relationEntity')
            || !property_exists($input, '_relationName')
            || !property_exists($input, '_relationData')
        ) {
            return;
        }

        $relationName = $this->getMetadata()->get(['entityDefs', $input->_relationEntity, 'links', $input->_relationName, 'relationName']);
        if (empty($relationName)) {
            return;
        }

        $relEntityType = ucfirst($relationName);
        $relInput      = $input->_relationData;

        if (empty($relInput)) {
            return;
        }

        $relId = property_exists($input, '_relationId') ? $input->_relationId : null;

        // find relation ID
        if (empty($relId)) {
            $where = [];
            foreach ($this->getMetadata()->get(['entityDefs', $relEntityType, 'fields']) as $f => $d) {
                if (empty($d['relationField'])) {
                    continue;
                }
                if ($this->getMetadata()->get(['entityDefs', $relEntityType, 'links', $f, 'entity']) === $entity->getEntityType()) {
                    $where["{$f}Id"] = $entity->get('id');
                } else {
                    $where["{$f}Id"] = $input->_relationEntityId;
                }
            }
            if (count($where) === 2) {
                $relEntity = $this->getEntityManager()->getRepository($relEntityType)->select(['id'])->where($where)->findOne();
                if (!empty($relEntity)) {
                    $relId = $relEntity->get('id');
                }
            }
        }

        if (!empty($relId)) {
            $relInput->_skipCheckForConflicts = true;
            try {
                $this->getServiceFactory()->create($relEntityType)->updateEntity($relId, $relInput);
            } catch (NotModified $e) {
            }
        }
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $this->prepareInputData($entity, $data);
        $this->validateLinkValue($entity, $data);
        $this->createLinkValue($entity, $data);
    }

    protected function afterCreateEntity(Entity $entity, $data)
    {
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        $this->prepareInputData($entity, $data);
        $this->validateLinkValue($entity, $data);
        $this->createLinkValue($entity, $data);
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
    }

    protected function prepareInputData(Entity $entity, \stdClass $input): void
    {
        foreach ($input as $field => $value) {
            if ($value instanceof \stdClass) {
                $fieldType = $entity->entityDefs['fields'][$field]['type'] ?? null;
                if ($fieldType !== null && !in_array($fieldType, ['link', 'linkMultiple', 'jsonArray', 'jsonObject'])) {
                    unset($input->$field);
                    $entity->clear($field);
                }
            }
        }
    }

    protected function createLinkValue(Entity $entity, \stdClass $input): void
    {
        if ($this->getEntityManager()->getUser()->isSystemUser()) {
            return;
        }

        foreach ($input as $field => $value) {
            if (empty($value)) {
                continue;
            }

            $fieldDefs = $entity->fields[$field] ?? null;

            if (empty($fieldDefs['isLinkEntity']) && empty($fieldDefs['isLinkMultipleCollection'])) {
                continue;
            }

            if (!empty($fieldDefs['isLinkEntity']) && property_exists($input, "{$field}Id")) {
                continue;
            }

            if (!empty($fieldDefs['isLinkMultipleCollection']) && property_exists($input, "{$field}Ids")) {
                continue;
            }

            $entityName = $entity->entityDefs['fields'][$field]['entity'] ?? $entity->entityDefs['links'][$field]['entity'] ?? null;
            if (empty($entityName)) {
                throw new BadRequest("Can't find Entity Name for the field '{$field}'.");
            }

            $relRepository = $this->getEntityManager()->getRepository($entityName);

            $relEntity = $relRepository->get();

            $values = !empty($fieldDefs['isLinkEntity']) ? [$value] : $value;

            foreach ($values as $row) {
                if (!$row instanceof \stdClass) {
                    break;
                }
                $insert = $where = [];
                foreach ($row as $relField => $relValue) {
                    $relFieldDefs = $relEntity->fields[$relField] ?? null;
                    if (
                        !empty($relFieldDefs['isLinkMultipleIdList'])
                        || !empty($relFieldDefs['attributeId'])
                        || !empty($relEntity->entityDefs['fields'][$relField]['notStorable'])
                        || !in_array($relFieldDefs['type'], ['id', 'foreignId', 'bool', 'varchar', 'text', 'datetime', 'date', 'float', 'int'])
                    ) {
                        throw new BadRequest("Unsupported link value field '{$field}'. Only simple (not virtual, not relation) fields are supported.");
                    }
                    if (in_array($relFieldDefs['type'], ['varchar', 'text', 'id', 'foreignId'])) {
                        $relValue = trim($relValue);
                    }
                    $where[$relField]  = $relValue;
                    $insert[$relField] = $relValue;
                }

                if (!empty($where['id'])) {
                    $where = ['id' => $where['id']];
                }

                $where      = $this->buildLinkValueWhere($entity, $field, $where, $row);
                $relRecords = $relRepository->where($where)->limit(0, 2)->find();

                if (!empty($relRecords[1])) {
                    throw new BadRequest("Multiple '{$entityName}' records match the provided data for field '{$field}'. Provide more specific values to uniquely identify or create a single record.");
                }

                $relRecord = $relRecords[0] ?? null;

                if (empty($relRecord)) {
                    $relRecord = $relRepository->get();
                    $insert    = $this->prepareLinkValueInsert($entity, $field, $insert, $row);
                    $relRecord->set($insert);
                    $this->getEntityManager()->saveEntity($relRecord);
                } else {
                    $this->enrichLinkValue($entity, $field, $relRecord, $row);
                }

                if (!empty($fieldDefs['isLinkEntity'])) {
                    $input->{"{$field}Id"} = $relRecord->get('id');
                    $entity->set("{$field}Id", $input->{"{$field}Id"});
                } elseif (!empty($fieldDefs['isLinkMultipleCollection'])) {
                    $input->{"{$field}Ids"}[] = $relRecord->get('id');
                    $entity->set("{$field}Ids", $input->{"{$field}Ids"});
                }
            }
            unset($input->$field);
            $entity->clear($field);
        }
    }

    protected function buildLinkValueWhere(Entity $entity, string $field, array $where, \stdClass $row): array
    {
        return $where;
    }

    protected function prepareLinkValueInsert(Entity $entity, string $field, array $insert, \stdClass $row): array
    {
        return $insert;
    }

    protected function enrichLinkValue(Entity $entity, string $field, Entity $relRecord, \stdClass $row): void
    {
    }

    protected function validateLinkValue(Entity $entity, \stdClass $input): void
    {
        if ($this->getEntityManager()->getUser()->isSystemUser()) {
            return;
        }

        foreach ($input as $field => $value) {
            if (empty($value)) {
                continue;
            }

            $fieldDefs = $entity->fields[$field] ?? null;

            if (empty($fieldDefs['isLinkEntityId']) && empty($fieldDefs['isLinkMultipleIdList'])) {
                continue;
            }

            if (!$entity->isNew() && !$entity->isAttributeChanged($field)) {
                continue;
            }

            $originalName = $fieldDefs['originalName'] ?? $fieldDefs['name'] ?? $field;
            $where        = $entity->entityDefs['fields'][$originalName]['where'] ?? [];

            if (!empty($where)) {
                $renderedWhere = @json_decode(
                    $this->getInjection('twig')->renderTemplate(json_encode($where), ['entity' => $entity]),
                    true
                );
                if (is_array($renderedWhere)) {
                    $where = $renderedWhere;
                }
            }

            $entityName = $entity->entityDefs['fields'][$originalName]['entity'] ?? $entity->entityDefs['links'][$originalName]['entity'] ?? null;
            if (empty($entityName)) {
                continue;
            }

            $type = $this->getMetadata()->get(['scopes', $entityName, 'type']);

            if (empty($type)) {
                continue;
            }

            if (!is_array($value)) {
                $value = [$value];
            }

            if ($type === 'ReferenceData') {
                foreach ($value as $id) {
                    $foreignEntity = $this->getEntityManager()->getRepository($entityName)->get($id);
                    if (empty($foreignEntity)) {
                        throw new BadRequest("'$value' is not valid for the field '$field'");
                    }
                }
                continue;
            }

            $mapper     = $this->getEntityManager()->getMapper();
            $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

            $sp           = $this->getSelectManager($entityName)->getSelectParams(['where' => $where], true, true);
            $sp['select'] = ['id'];
            $qb           = $mapper->createSelectQueryBuilder($this->getEntityManager()->getRepository($entityName)->get(), $sp);

            $qb->andWhere("$tableAlias.id IN (:ids)");
            $qb->setParameter('ids', $value, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
            $res = $qb->fetchFirstColumn();

            sort($value);
            sort($res);

            if ($value !== $res) {
                throw new BadRequest("'{$value[0]}' is not valid for the field '$field'");
            }
        }
    }

    protected function isArchivedRecord(?Entity $entity): bool
    {
        return !empty($entity)
            && !empty($this->getMetadata()->get(['scopes', $entity->getEntityName(), 'hasArchive']))
            && !empty($entity->get('isArchived'));
    }

    protected function hasArchivedRecords(string $entityName, array $ids): bool
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids) || empty($this->getMetadata()->get(['scopes', $entityName, 'hasArchive']))) {
            return false;
        }

        $archived = $this->getEntityManager()->getRepository($entityName)
            ->where(['id' => $ids, 'isArchived' => true])
            ->findOne();

        return !empty($archived);
    }

    /**
     * A relation with an archived record cannot be created or removed via link fields of the input.
     */
    protected function validateArchivedLinks(Entity $entity, \stdClass $data): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links'], []) as $link => $linkDefs) {
            if (empty($linkDefs['entity']) || empty($linkDefs['type'])) {
                continue;
            }

            $ids = [];
            if ($linkDefs['type'] === 'belongsTo' && property_exists($data, "{$link}Id")) {
                $oldId = $entity->isNew() ? null : $entity->get("{$link}Id");
                $newId = $data->{"{$link}Id"};
                if ((string)$oldId !== (string)$newId) {
                    $ids = [$oldId, $newId];
                }
            } elseif ($linkDefs['type'] === 'hasMany' && property_exists($data, "{$link}Ids") && is_array($data->{"{$link}Ids"})) {
                $oldIds = $entity->isNew() ? [] : ($entity->getLinkMultipleIdList($link) ?? []);
                $newIds = $data->{"{$link}Ids"};
                $ids = array_merge(array_diff($oldIds, $newIds), array_diff($newIds, $oldIds));
            }

            if ($this->hasArchivedRecords($linkDefs['entity'], $ids)) {
                throw new BadRequest($this->getInjection('language')->translate('archivedRecordCannotBeLinked', 'exceptions', 'Global'));
            }
        }
    }

    protected function validateArchivedRelation(Entity $entity, Entity $foreignEntity): void
    {
        if ($this->isArchivedRecord($entity) || $this->isArchivedRecord($foreignEntity)) {
            throw new BadRequest($this->getInjection('language')->translate('archivedRecordCannotBeLinked', 'exceptions', 'Global'));
        }
    }

    public function getDuplicateAttributes($id)
    {
        if (!$this->getAcl()->check($this->getEntityType(), 'read')) {
            throw new Forbidden();
        }

        if (empty($id)) {
            throw new BadRequest();
        }

        $entity = $this->getEntity($id);

        if (!$entity) {
            throw new NotFound();
        }

        $attributes = $entity->getValueMap();
        unset($attributes->id);
        unset($attributes->createdAt);
        unset($attributes->modifiedAt);

        $fields = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], array());

        $duplicatableRelations = $this->getMetadata()->get(['scopes', $this->getEntityType(), 'duplicatableRelations'], []);

        foreach ($fields as $field => $item) {
            if (empty($item['type'])) continue;
            $type = $item['type'];

            if (!empty($item['relationVirtualField'])) {
                Util::unsetProperty($attributes, $field);
                Util::unsetProperty($attributes, "{$field}Id");
                Util::unsetProperty($attributes, "{$field}Name");
                Util::unsetProperty($attributes, "{$field}Ids");
                Util::unsetProperty($attributes, "{$field}Names");
                continue;
            }

            if (!empty($item['duplicateIgnore'])) {
                $attributeToIgnoreList = $this->getFieldManagerUtil()->getAttributeList($this->entityType, $field);
                foreach ($attributeToIgnoreList as $attribute) {
                    unset($attributes->$attribute);
                }
                continue;
            }

            if ($type === 'linkMultiple') {
                if (!in_array($field, $duplicatableRelations)) {
                    unset($attributes->{$field . 'Ids'});
                    unset($attributes->{$field . 'Names'});
                    unset($attributes->{$field . 'Columns'});
                }
            }
        }

        $attributes->_duplicatingEntityId = $id;

        return $this->prepareDuplicateAttributes((string)$id, $attributes);
    }

    protected function afterCreateProcessDuplicating(Entity $entity, $data)
    {
        if (!isset($data->_duplicatingEntityId) || !empty($this->getMemoryStorage()->get('importJobId'))) return;

        $duplicatingEntityId = $data->_duplicatingEntityId;
        if (!$duplicatingEntityId) return;
        $duplicatingEntity = $this->getEntityManager()->getEntity($entity->getEntityType(), $duplicatingEntityId);
        if (!$duplicatingEntity) return;
        if (!$this->getAcl()->check($duplicatingEntity, 'read')) return;

        $this->duplicateLinks($entity, $duplicatingEntity);
    }

    /**
     * @param Entity $entity
     * @param Entity $duplicatingEntity
     */
    protected function duplicateLinks(Entity $entity, Entity $duplicatingEntity)
    {
        // get all links
        $allLinks = $this->getEntityLinks($entity);

        $nonDuplicatableRelations = $this->getMetadata()->get(['app', 'nonDuplicatableRelations'], []);

        // prepare links
        foreach ($allLinks as $field => $row) {
            if (!empty($row['type']) && $row['type'] == 'hasMany' && !in_array($field, $nonDuplicatableRelations)) {
                $links[] = $field;
            }
        }

        if (!empty($links)) {
            $duplicatableRelations = $this->getMetadata()->get(['scopes', $this->getEntityType(), 'duplicatableRelations'], []);

            foreach ($links as $link) {
                if (!in_array($link, $duplicatableRelations)) {
                    continue;
                }

                // prepare method name
                $methodName = 'duplicate' . ucfirst($link);

                /** @var AbstractRecordService $handler */
                $handler = $this
                    ->getInjection('eventManager')
                    ->dispatch(
                        $entity->getEntityType() . 'Service', 'beforeDuplicateLink',
                        new Event(
                            [
                                'handler'           => clone $this,
                                'entity'            => $entity,
                                'duplicatingEntity' => $duplicatingEntity,
                                'link'              => $link
                            ]
                        )
                    )
                    ->getArgument('handler');

                // call custom method
                if (method_exists($handler, $methodName)) {
                    try {
                        $handler->{$methodName}($entity, $duplicatingEntity);
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error($e->getMessage());
                    }
                    continue 1;
                }

                if (!empty($allLinks[$link]['relationName'])) {
                    $data = $duplicatingEntity->get($link) ?? [];
                    if (count($data) > 0) {
                        foreach ($data as $item) {
                            try {
                                $this->getEntityManager()->getRepository($entity->getEntityType())->relate($entity, $link, $item, null, ['duplicatingEntityId' => $duplicatingEntity->get('id')]);
                            } catch (\Throwable $e) {
                                $GLOBALS['log']->error($e->getMessage());
                            }
                        }
                    }
                } else {
                    $foreignEntity = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $link, 'entity']);
                    if (!empty($foreignEntity)) {
                        $foreign = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $link, 'foreign']);
                        if (!empty($foreign)) {
                            foreach ($duplicatingEntity->get($link) ?? [] as $item) {
                                $record = $this->getEntityManager()->getEntity($foreignEntity);
                                $data   = $item->toArray();
                                unset($data['id']);

                                foreach ($data as $field => $value) {
                                    if (!empty($this->getMetadata()->get(['entityDefs', $foreignEntity, 'fields', $field, 'duplicateIgnore']))) {
                                        $attributeToIgnoreList = $this->getFieldManagerUtil()->getAttributeList($foreignEntity, $field);
                                        foreach ($attributeToIgnoreList as $attribute) {
                                            unset($data[$attribute]);
                                        }
                                        continue;
                                    }

                                    // remove unique fields
                                    if (!empty($record->fields[$field]['unique'])) {
                                        unset($data[$field]);
                                    }

                                    if (in_array($field, ['createdAt', 'modifiedAt', 'createdById', 'modifiedById'])) {
                                        unset($data[$field]);
                                    }
                                }

                                $record->set($data);
                                $record->set($foreign . 'Id', $entity->get('id'));
                                $record->set($foreign . 'Name', $entity->get('name'));

                                $scope = $entity->getEntityType();
                                if (!empty($this->getMetadata()->get(['entityDefs', $scope, 'links', $link, 'isMainAssociateRelation'])) ||
                                    !empty($this->getMetadata()->get(['entityDefs', $scope, 'links', $link, 'isRelatedAssociateRelation']))) {
                                    $record->clear("reverseAssociated{$scope}Id");
                                }

                                try {
                                    $this->getEntityManager()->saveEntity($record);
                                } catch (\Throwable $e) {
                                    $GLOBALS['log']->error("Creating '$foreignEntity' failed: {$e->getMessage()}");
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Entity $entity
     *
     * @return array
     */
    protected function getEntityLinks(Entity $entity): array
    {
        return $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links'], []);
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $event = $this->dispatchEvent('beforeCheckingIsEntityUpdated', new Event(['entity' => $entity, 'service' => $this, 'data' => $data]));

        $entity = $event->getArgument('entity');
        $data   = $event->getArgument('data');

        // return true if relation virtual file has been changed
        if (property_exists($data, '_relationData') && !empty($data->_relationData)) {
            return true;
        }

        if (property_exists($data, '__attributesToRemove') && !empty($data->__attributesToRemove)) {
            foreach ($data->__attributesToRemove as $id) {
                foreach ($entity->entityDefs['fields'] as $field => $fieldDefs) {
                    if (!empty($fieldDefs['attributeId']) && $fieldDefs['attributeId'] === $id && !empty($fieldDefs['attributeValueId'])) {
                        return true;
                    }
                }
            }
        }

        $skip = [
            'id',
            'deleted'
        ];

        foreach (['createdAt', 'modifiedAt', 'createdById'] as $v) {
            if (!property_exists($data, $v)) {
                $skip[] = $v;
            }
        }

        $linkNames         = [];
        $linkMultipleIds   = [];
        $linkMultipleNames = [];
        foreach ($entity->entityDefs['fields'] ?? [] as $name => $fieldData) {
            if (isset($fieldData['type'])) {
                if (in_array($fieldData['type'], ['link', 'file'])) {
                    $linkNames[] = $name . 'Name';
                }
                if ($fieldData['type'] === 'linkMultiple') {
                    $linkMultipleIds[]   = $name . 'Ids';
                    $linkMultipleNames[] = $name . 'Names';
                }
            }
        }

        foreach ($linkMultipleIds as $key) {
            if (property_exists($data, $key) && !is_array($data->{$key})) {
                $data->$key = [];
            }
        }

        foreach ($entity->getFields() as $field => $params) {
            if (in_array($field, $skip)) {
                continue 1;
            }

            if (!isset($params['type'])) {
                continue 1;
            }

            // it needs for giving ability to create attributes
            if (!$entity->hasFetched($field) && !empty($params['attributeId']) && !$entity->hasAttributeValue($field) && empty($params['notStorable'])) {
                return true;
            }

            if (!property_exists($data, $field)) {
                continue 1;
            }

            if (in_array($field, $linkNames)) {
                continue 1;
            }

            if (in_array($field, $linkMultipleNames)) {
                continue 1;
            }

            if ($entity->isFieldValueLocked($field)) {
                continue;
            }

            if (in_array($field, $linkMultipleIds)) {
                $collection = $entity->get(substr($field, 0, -3));
                if (!empty($collection) && count($collection) > 0) {
                    $value = array_column($collection->toArray(), 'id');
                } elseif (!empty($entity->get($field))) {
                    $value = $entity->get($field);
                } else {
                    $value = [];
                }

                $value = array_unique($value);
                sort($value);

                $data->$field = array_unique($data->$field);
                sort($data->$field);
            } elseif (isset($entity->_technicalFieldValues[$field])) {
                $value = $entity->_technicalFieldValues[$field];
            } else {
                $value = $entity->get($field);
            }

            // strict type for NULL
            if (($data->$field === null && $value !== null) || ($data->$field !== null && $value === null)) {
                return true;
            }

            if (!$this->areValuesEqual($entity, $field, $data->$field, $value)) {
                return true;
            }
        }

        return false;
    }

    protected function areValuesEqual(Entity $entity, string $field, $value1, $value2): bool
    {
        if (!isset($entity->getFields()[$field]['type'])) {
            return false;
        }

        $type = $entity->getFields()[$field]['type'];

        if (in_array($type, [Entity::JSON_ARRAY, Entity::JSON_OBJECT])) {
            if (is_string($value1)) {
                $value1 = Json::decode($value1, true);
            }
            if (is_string($value2)) {
                $value2 = Json::decode($value2, true);
            }
        }

        return Entity::areValuesEqual($type, $value1, $value2);
    }

    /**
     * @param Entity    $entity
     * @param \stdClass $data
     *
     * @return array
     */
    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        if (!empty($this->getMemoryStorage()->get('importJobId')) || $this->isPseudoTransaction()) {
            return [];
        }

        $data = (array)$data;
        $prev = (array)($data['_prev'] ?? []);

        if (empty($prev)) {
            return [];
        }

        unset($data['_prev']);
        unset($data['_silentMode']);
        unset($data['_skipIsEntityUpdated']);

        $fieldsThatConflict = [];
        foreach ($data as $field => $newValue) {
            if ($field == 'data' || str_ends_with($field, 'Name') || str_ends_with($field, 'Names') || mb_substr($field, 0, 8) === 'complete') {
                continue 1;
            }

            // for link multiple
            if (str_ends_with($field, 'Ids') && !empty($collection = $entity->get(substr($field, 0, -3))) && $collection instanceof EntityCollection) {
                $entity->set($field, array_column($collection->toArray(), 'id'));
            }

            $fieldDefs = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields', $field], []);

            // skip if field is part of field
            if (!empty($fieldDefs['mainField'])) {
                continue 1;
            }

            if ($entity->has($field) && array_key_exists($field, $prev)) {
                $prevValue = $prev[$field];
                $type      = $entity->fields[$field]['type'] ?? null;
                if (!is_null($prevValue)) {
                    if ($type === 'int') {
                        $prevValue = (int)$prevValue;
                    } else if ($type === 'float') {
                        $prevValue = (float)$prevValue;
                    } else if (is_object($prevValue)) {
                        $prevValue = json_decode(json_encode($prevValue), true);;
                    }
                }

                if (Util::toMd5($entity->get($field)) != Util::toMd5($prevValue)) {
                    foreach (['Id', 'Ids', 'Unit'] as $suffix) {
                        $name = str_ends_with($field, $suffix) ? substr($field, 0, -strlen($suffix)) : $field;
                        $type = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields', $name, 'type'], '');

                        if (!empty($type) && in_array($type, ['link', 'linkMultiple', 'unit'])) {
                            $field = $name;
                        }

                    }
                    $fieldsThatConflict[$field] = $this->getInjection('language')->translate($field, 'fields', $this->entityName);
                }
            }
        }

        return $fieldsThatConflict;
    }

    protected function isRequiredFieldViaConditions(string $field, Entity $entity): bool
    {
        $conditions = $entity->entityDefs['fields'][$field]['conditionalProperties']['required']['conditionGroup'] ??
            $this->getMetadata()->get("entityDefs.{$entity->getEntityType()}.fields.{$field}.conditionalProperties.required.conditionGroup");
        if (!empty($conditions)) {
            $result = $this->getConditionChecker()->check($entity, $conditions);
            if ($result !== null) {
                return $result;
            }
        }

        return false;
    }

    protected function isProtectedFieldViaConditions(string $field, Entity $entity): bool
    {
        $conditions = $entity->entityDefs['fields'][$field]['conditionalProperties']['protected']['conditionGroup'] ??
            $this->getMetadata()->get("entityDefs.{$entity->getEntityType()}.fields.{$field}.conditionalProperties.protected.conditionGroup");
        if (!empty($conditions)) {
            $result = $this->getConditionChecker()->check($entity, $conditions);
            if ($result !== null) {
                return $result;
            }
        }

        return false;
    }

    /**
     * @param Entity $entity
     * @param        $field
     * @return bool
     */
    private function isNullField(Entity $entity, $field): bool
    {
        $isNull = is_null($entity->get($field)) || $entity->get($field) === '';
        $fields = $entity->getFields();
        if ($isNull && !empty($fields[$field]['relation'])) {
            $relationValue = $entity->get($fields[$field]['relation']);
            if ($relationValue instanceof \Espo\ORM\EntityCollection) {
                $isNull = $relationValue->count() === 0;
            } else {
                $isNull = is_null($relationValue);
            }
        }

        return $isNull;
    }

    protected function getDefaultRepositoryOptions(): array
    {
        return [
            'pseudoTransactionId'      => $this->getPseudoTransactionId(),
            'pseudoTransactionManager' => $this->getPseudoTransactionManager()
        ];
    }

    protected function getUniqueIndexDescriber(): UniqueIndexDescriber
    {
        return $this->getInjection(UniqueIndexDescriber::class);
    }

    /**
     * The fields that cannot be changed in the record, while the other records of the entity can have them changed.
     */
    protected function getReadOnlyFields(Entity $entity): array
    {
        return [];
    }

    /**
     * The attributes of the duplicate of the record, prepared by the entity type.
     */
    protected function prepareDuplicateAttributes(string $id, \stdClass $attributes): \stdClass
    {
        return $attributes;
    }

    /**
     * Checks the input of the record before it is applied to the record.
     */
    protected function validateEntityInput(Entity $entity, \stdClass $data): void
    {
    }

    /**
     * Prepares the record created or updated by the input before it is validated and stored.
     */
    protected function prepareEntityToStore(Entity $entity, \stdClass $data): void
    {
    }

    /**
     * Checks the record before it is stored.
     */
    protected function validateEntityBeforeStore(Entity $entity, \stdClass $data): void
    {
    }
}
