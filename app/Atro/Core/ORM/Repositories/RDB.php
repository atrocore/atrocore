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

namespace Atro\Core\ORM\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotUnique;
use Atro\ORM\DB\RDB\Mapper;
use Atro\Core\EventManager\Event;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Interfaces\Injectable;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityFactory;
use Espo\ORM\EntityManager;

class RDB extends \Espo\ORM\Repositories\RDB implements Injectable
{
    protected $dependencies
        = [
            'container',
            'connection',
            'metadata',
            'config',
            'fieldManagerUtil',
            'eventManager',
            'aclManager'
        ];

    protected $injections = [];

    protected $processFieldsAfterSaveDisabled = false;
    protected $processFieldsBeforeSaveDisabled = false;
    protected $processFieldsAfterRemoveDisabled = false;

    protected function addDependency($name): void
    {
        $this->dependencies[] = $name;
    }

    protected function addDependencyList(array $list): void
    {
        foreach ($list as $item) {
            $this->addDependency($item);
        }
    }

    public function inject($name, $object): void
    {
        $this->injections[$name] = $object;
    }

    protected function getInjection($name)
    {
        return $this->injections[$name];
    }

    public function getDependencyList()
    {
        return $this->dependencies;
    }

    /**
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    /**
     * @return Config
     */
    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    protected function getFieldManagerUtil()
    {
        return $this->getInjection('fieldManagerUtil');
    }

    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        parent::__construct($entityType, $entityManager, $entityFactory);

        $this->init();
    }

    protected function init()
    {
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        $this->dispatch('beforeRemove', $entity, $options);

        $nowString = date('Y-m-d H:i:s');
        if ($entity->hasAttribute('modifiedAt')) {
            $entity->set('modifiedAt', $nowString);
        }
        if ($entity->hasAttribute('modifiedById')) {
            $user = $this->getEntityManager()->getUser();
            $modifiedById = empty($user) ? 'system' : $user->id;
            $entity->set('modifiedById', $modifiedById);
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->dispatch('afterRemove', $entity, $options);
    }

    public function deleteFromDb(string $id): bool
    {
        $deleteRelEntities = !empty($this->getMemoryStorage()->get('deleteRelEntities'));

        if (!$deleteRelEntities) {
            /** @var Entity $entity */
            $entity = $this->getMapper()->selectById($this->entityFactory->create($this->entityType), $id, ['withDeleted' => true]);
        }

        $res = parent::deleteFromDb($id);

        // remove all many-many relation entities
        if (!$deleteRelEntities && !empty($entity)) {
            $this->getMemoryStorage()->set('deleteRelEntities', true);
            foreach ($entity->getRelations() as $defs) {
                if (!empty($defs['relationName']) && !empty($defs['key']) && !empty($defs['midKeys'][0])) {
                    $repository = $this->getEntityManager()->getRepository(ucfirst($defs['relationName']));
                    while (true) {
                        $collection = $repository
                            ->select(['id'])
                            ->where([$defs['midKeys'][0] => $entity->get($defs['key'])])
                            ->limit(0, $this->getConfig()->get('removeCollectionPart', 2000))
                            ->find();
                        if (empty($collection[0])) {
                            break;
                        }
                        foreach ($collection as $item) {
                            $repository->deleteFromDb($item->get('id'));
                        }
                    }
                }
            }
        }

        return $res;
    }

    protected function beforeMassRelate(Entity $entity, $relationName, array $params = [], array $options = [])
    {
        parent::beforeMassRelate($entity, $relationName, $params, $options);

        $this->dispatch('beforeMassRelate', $entity, $options, $relationName, $params);
    }

    protected function afterMassRelate(Entity $entity, $relationName, array $params = [], array $options = [])
    {
        parent::afterMassRelate($entity, $relationName, $params, $options);

        $this->dispatch('afterMassRelate', $entity, $options, $relationName, $params);
    }

    public function markedAsDeleted(string $id): bool
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->getMapper()->toDb($this->entityType))
            ->where('id=:id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return !empty($res);
    }

    public function restore(string $id)
    {
        $this->beforeRestore($id);

        $result = $this->getConnection()
            ->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier(Util::toUnderScore(lcfirst($this->entityType))))
            ->set('deleted', ':false')
            ->where('id=:id')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $id)
            ->executeQuery();

        if ($result) {
            $entity = $this->get($id);
            $this->afterRestore($entity);
        }

        return $entity ?? false;
    }

    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);

        // dispatch an event
        $this->dispatch('beforeRelate', $entity, $options, $relationName, $data, $foreign);
    }

    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        parent::afterRelate($entity, $relationName, $foreign, $data, $options);

        // dispatch an event
        $this->dispatch('afterRelate', $entity, $options, $relationName, $data, $foreign);
    }

    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        parent::beforeUnrelate($entity, $relationName, $foreign, $options);

        // dispatch an event
        $this->dispatch('beforeUnrelate', $entity, $options, $relationName, null, $foreign);
    }

    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        parent::afterUnrelate($entity, $relationName, $foreign, $options);

        // dispatch an event
        $this->dispatch('afterUnrelate', $entity, $options, $relationName, null, $foreign);
    }

    protected function validateFieldsByType(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], []) as $fieldName => $fieldData) {
            if (isset($fieldData['type'])) {
                $method = "validate" . ucfirst($fieldData['type']);
                if (method_exists($this, $method)) {
                    $this->$method($entity, $fieldName, $fieldData);
                }
            }
        }
    }

    protected function prepareFieldsByType(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], []) as $fieldName => $fieldData) {
            if (isset($fieldData['type'])) {
                $method = "prepareFieldType" . ucfirst($fieldData['type']);
                if (method_exists($this, $method)) {
                    $this->$method($entity, $fieldName, $fieldData);
                }
            }
        }
    }

    protected function validateEmail(Entity $entity, string $fieldName, array $fieldData): void
    {
        if ($entity->isAttributeChanged($fieldName) && !empty($entity->get($fieldName))) {
            if (!filter_var($entity->get($fieldName), FILTER_VALIDATE_EMAIL)) {
                throw new BadRequest(
                    sprintf($this->getLanguage()->translate('emailIsInvalid', 'exceptions'), $this->getLanguage()->translate($fieldName, 'fields', $entity->getEntityType()))
                );
            }
        }
    }

    protected function validateFile(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!empty($fieldData['fileTypeId']) && $entity->isAttributeChanged($fieldName . 'Id') && !empty($entity->get($fieldName . 'Id'))) {
            $file = $this->getEntityManager()->getRepository('File')->get($entity->get($fieldName . 'Id'));
            if (!empty($file) && $file->get('typeId') !== $fieldData['fileTypeId']) {
                throw new BadRequest(
                    sprintf($this->getLanguage()->translate('fileIsInvalid', 'exceptions'), $this->getLanguage()->translate($fieldName, 'fields', $entity->getEntityType()))
                );
            }
        }
    }

    protected function validateRangeValue(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (empty($fieldData['mainField'])) {
            return;
        }

        $type = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields', $fieldData['mainField'], 'type']);
        if (!in_array($type, ['rangeInt', 'rangeFloat'])) {
            return;
        }

        $fromName = $fieldData['mainField'] . 'From';
        $toName = $fieldData['mainField'] . 'To';

        if (!$entity->isAttributeChanged($fromName) && $entity->isAttributeChanged($toName)) {
            return;
        }

        if ($entity->get($toName) !== null && $entity->get($fromName) !== null && $entity->get($fromName) > $entity->get($toName)) {
            $fieldLabel = $this->getLanguage()->translate($toName, 'fields', $entity->getEntityType());
            $message = str_replace(['{field}', '{value}'], [$fieldLabel, $entity->get($fromName)], $this->getLanguage()->translate('fieldShouldBeGreater', 'messages', 'Global'));
            throw new BadRequest($message);
        }
    }

    protected function validateInt(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!$entity->isAttributeChanged($fieldName)) {
            return;
        }

        $this->validateRangeValue($entity, $fieldName, $fieldData);
    }

    protected function validateFloat(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!$entity->isAttributeChanged($fieldName)) {
            return;
        }

        $this->validateRangeValue($entity, $fieldName, $fieldData);

        if (isset($fieldData['amountOfDigitsAfterComma']) && !empty($entity->get($fieldName))) {
            $roundValue = $this->roundValueUsingAmountOfDigitsAfterComma($entity->get($fieldName), $fieldData['amountOfDigitsAfterComma']);
            $entity->set($fieldName, (float)$roundValue);
        }
    }

    protected function validateEnum(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!$entity->isAttributeChanged($fieldName)) {
            return;
        }

        if (!isset($fieldData['view']) && $entity->isAttributeChanged($fieldName) && !empty($entity->get($fieldName))) {
            $fieldOptions = !isset($fieldData['optionsIds']) ? $fieldData['options'] : $fieldData['optionsIds'];
            if (empty($fieldOptions) && !empty($fieldData['groupOptions'])) {
                $fieldOptions = [];
                foreach ($fieldData['groupOptions'] as $group) {
                    $fieldOptions = array_merge($fieldOptions, $group['options'] ?? []);
                }
            }
            if (empty($fieldOptions) && $fieldData['type'] === 'multiEnum' || !empty($fieldData['relationVirtualField'])) {
                return;
            }

            $value = $entity->get($fieldName);

            if ($fieldData['type'] == 'enum') {
                $value = [$value];
            }

            if (!is_array($value)) {
                return;
            }

            foreach ($value as $v) {
                if (!in_array($v, $fieldOptions)) {
                    throw new BadRequest(
                        sprintf(
                            $this->getLanguage()->translate('noSuchOptions', 'exceptions', 'Global'), $v,
                            $this->getLanguage()->translate($fieldName, 'fields', $entity->getEntityType())
                        )
                    );
                }
            }
        }
    }

    protected function validateMultiEnum(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!$entity->isAttributeChanged($fieldName)) {
            return;
        }

        $this->validateEnum($entity, $fieldName, $fieldData);
    }

    protected function validateExtensibleEnum(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!$entity->isAttributeChanged($fieldName)) {
            return;
        }

        if ($entity->isAttributeChanged($fieldName) && !empty($id = $entity->get($fieldName))) {
            $option = $this->getEntityManager()->getRepository('ExtensibleEnumOption')->getPreparedOption($fieldData['extensibleEnumId'], $id);
            if (!empty($option['notExistingOption'])) {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()->translate('noSuchOptions', 'exceptions', 'Global'), $option['id'],
                        $this->getLanguage()->translate($fieldName, 'fields', $entity->getEntityType())
                    )
                );
            }
        }
    }

    protected function validateExtensibleMultiEnum(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!$entity->isAttributeChanged($fieldName)) {
            return;
        }

        if ($entity->isAttributeChanged($fieldName) && !empty($ids = $entity->get($fieldName))) {
            $options = $this->getEntityManager()->getRepository('ExtensibleEnumOption')->getPreparedOptions($fieldData['extensibleEnumId'], $ids);
            foreach ($options as $option) {
                if (!empty($option['notExistingOption'])) {
                    throw new BadRequest(
                        sprintf(
                            $this->getLanguage()->translate('noSuchOptions', 'exceptions', 'Global'), $option['id'],
                            $this->getLanguage()->translate($fieldName, 'fields', $entity->getEntityType())
                        )
                    );
                }
            }
        }
    }

    protected function prepareFieldTypeMultiEnum(Entity $entity, string $fieldName, array $fieldData): void
    {
        $this->prepareFieldTypeArray($entity, $fieldName, $fieldData);
    }

    protected function prepareFieldTypeArray(Entity $entity, string $fieldName, array $fieldData): void
    {
        $value = $entity->get($fieldName);
        if (is_array($value)) {
            $value = array_values(array_unique($value));
            $entity->set($fieldName, $value);
        }
    }

    protected function prepareFieldTypeDate(Entity $entity, string $fieldName, array $fieldData): void
    {
        if ($entity->isAttributeChanged($fieldName)) {
            if (!empty($value = $entity->get($fieldName)) && array_key_exists('defaultDate', $fieldData) && !empty($modifier = $fieldData['defaultDate'])) {
                $entity->set($fieldName, $this->convertDateWithModifier($value, $modifier));
            }
        }
    }

    protected function prepareFieldTypeDateTime(Entity $entity, string $fieldName, array $fieldData): void
    {
        if ($entity->isAttributeChanged($fieldName)) {
            if (!empty($value = $entity->get($fieldName)) && array_key_exists('defaultDate', $fieldData) && !empty($modifier = $fieldData['defaultDate'])) {
                $entity->set($fieldName, $this->convertDateWithModifier($value, $modifier, 'Y-m-d H:i:s'));
            }
        }
    }

    protected function prepareFieldTypeWysiwyg(Entity $entity, string $fieldName, array $fieldData): void
    {
        if ($entity->isAttributeChanged($fieldName) && !empty($fieldData['htmlSanitizerId']) && !empty($entity->get($fieldName))) {
            /** @var \Atro\Core\Templates\Entities\ReferenceData $htmlSanitizer */
            $htmlSanitizer = $this->getEntityManager()->getRepository('HtmlSanitizer')->get($fieldData['htmlSanitizerId']);

            /** @var \Atro\Core\Utils\HTMLSanitizer $htmlSanitizerUtil */
            $htmlSanitizerUtil = $this->getInjection('container')->get('htmlSanitizer');

            $safeHtml = $htmlSanitizerUtil->sanitize($entity->get($fieldName), $htmlSanitizer->get('configuration'));
            $entity->set($fieldName, $safeHtml);
        }
    }

    public function convertDateWithModifier(string $date, string $modifier, string $format = 'Y-m-d'): string
    {
        $dt = new \DateTime($date);
        $dt->modify($modifier);
        return $dt->format($format);
    }

    protected function roundValueUsingAmountOfDigitsAfterComma($value, $amountOfDigitsAfterComma)
    {
        if (empty($value) || empty($amountOfDigitsAfterComma)) {
            return $value;
        }

        $roundedValue = number_format((float)$value, $amountOfDigitsAfterComma, '.', '');

        return (float)$roundedValue;
    }

    protected function validateVarchar(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!$entity->isAttributeChanged($fieldName)) {
            return;
        }

        if (!empty($fieldData['notNull']) && $entity->get($fieldName) === null) {
            $entity->set($fieldName, '');
        }

        $this->validateText($entity, $fieldName, $fieldData);

        if (!empty($fieldData['unitIdField']) && !empty($fieldData['measureId'])) {
            $unit = $this->getEntityManager()->getRepository('Unit')
                ->where([
                    'id'        => $entity->get($fieldName),
                    'measureId' => $fieldData['measureId']
                ])
                ->findOne();

            if (empty($unit)) {
                $fieldLabel = $this->getLanguage()->translate($fieldName, 'fields', $entity->getEntityType());
                throw new BadRequest(sprintf($this->getLanguage()->translate('noSuchUnit', 'exceptions', 'Global'), $entity->get($fieldName), $fieldLabel));
            }

            /**
             * Convert unit values
             */
            if (!empty($convertTo = $unit->get('convertTo'))) {
                /** @var \Espo\Repositories\Measure $measureRepository */
                $measureRepository = $this->getEntityManager()->getRepository('Measure');

                $mainField = $fieldData['mainField'];

                $mainFieldDefs = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $mainField]);
                switch ($mainFieldDefs['type']) {
                    case 'rangeInt':
                        $allUnits = $measureRepository->convertMeasureUnit($entity->get($mainField . 'From'), $unit->get('measureId'), $unit->get('id'));
                        $val = number_format((float)$allUnits[$convertTo->get('name')], 0);
                        $entity->set($mainField . 'From', (int)$val);

                        $allUnits = $measureRepository->convertMeasureUnit($entity->get($mainField . 'To'), $unit->get('measureId'), $unit->get('id'));
                        $val = number_format((float)$allUnits[$convertTo->get('name')], 0);
                        $entity->set($mainField . 'To', (int)$val);
                        break;
                    case 'rangeFloat':
                        $allUnits = $measureRepository->convertMeasureUnit($entity->get($mainField . 'From'), $unit->get('measureId'), $unit->get('id'));
                        $val = $allUnits[$convertTo->get('name')];
                        if (isset($mainFieldDefs['amountOfDigitsAfterComma'])) {
                            $val = number_format((float)$val, $mainFieldDefs['amountOfDigitsAfterComma']);
                        }
                        $entity->set($mainField . 'From', (float)$val);

                        $allUnits = $measureRepository->convertMeasureUnit($entity->get($mainField . 'To'), $unit->get('measureId'), $unit->get('id'));
                        $val = $allUnits[$convertTo->get('name')];
                        if (isset($mainFieldDefs['amountOfDigitsAfterComma'])) {
                            $val = number_format((float)$val, $mainFieldDefs['amountOfDigitsAfterComma']);
                        }
                        $entity->set($mainField . 'To', (float)$val);
                        break;
                    case 'int':
                        $allUnits = $measureRepository->convertMeasureUnit($entity->get($mainField), $unit->get('measureId'), $unit->get('id'));
                        $val = number_format((float)$allUnits[$convertTo->get('name')], 0);
                        $entity->set($mainField, (int)$val);
                        break;
                    case 'float':
                        $allUnits = $measureRepository->convertMeasureUnit($entity->get($mainField), $unit->get('measureId'), $unit->get('id'));
                        $val = $allUnits[$convertTo->get('name')];
                        if (isset($mainFieldDefs['amountOfDigitsAfterComma'])) {
                            $val = number_format($val, $mainFieldDefs['amountOfDigitsAfterComma']);
                        }
                        $entity->set($mainField, (float)$val);
                        break;
                }

                $entity->set($mainField . 'UnitId', $convertTo->get('id'));
            }
        }
    }

    protected function validateText(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!$entity->isAttributeChanged($fieldName)) {
            return;
        }

        if (!isset($fieldData['maxLength'])) {
            return;
        }

        $countBytesInsteadOfCharacters = (bool)$entity->get('countBytesInsteadOfCharacters');
        $fieldValue = (string)$entity->get($fieldName);
        $length = $countBytesInsteadOfCharacters ? strlen($fieldValue) : mb_strlen($fieldValue);

        $maxLength = (int)$fieldData['maxLength'];

        if ($length > $maxLength) {
            $fieldLabel = $this->getLanguage()->translate($fieldName, 'fields', $entity->getEntityType());
            throw new BadRequest(sprintf($this->getLanguage()->translate('maxLengthIsExceeded', 'exceptions', 'Global'), $fieldLabel, $maxLength, $length));
        }
    }

    protected function validateWysiwyg(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!$entity->isAttributeChanged($fieldName)) {
            return;
        }

        $this->validateText($entity, $fieldName, $fieldData);
    }

    protected function validateBool(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!$entity->isAttributeChanged($fieldName)) {
            return;
        }

        if ($entity->get($fieldName) !== null) {
            $entity->set($fieldName, !empty($entity->get($fieldName)));
        } elseif (!isset($fieldData['notNull']) || $fieldData['notNull'] === true) {
            $entity->set($fieldName, !empty($fieldData['default']));
        }

    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('container')->get('language');
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $this->prepareFieldsByType($entity);

        if (empty($options['skipAll'])) {
            $this->validateFieldsByType($entity);
        }

        // dispatch an event
        $this->dispatch('beforeSave', $entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if (!$this->processFieldsAfterSaveDisabled && empty($options['skipProcessFieldsAfterSave'])) {
            $this->processSpecifiedRelationsSave($entity, $options);
            if (empty($entity->skipProcessFileFieldsSave)) {
                $this->processFileFieldsSave($entity);
            }
            $this->processWysiwygFieldsSave($entity);
        }

        $this->updateModifiedAtForIntermediateEntities($entity);

        // dispatch an event
        $this->dispatch('afterSave', $entity, $options);
    }

    protected function updateModifiedAtForIntermediateEntities(Entity $entity)
    {
        foreach ($this->getMetadata()->get(['scopes', $this->entityType, 'modifiedExtendedIntermediateRelations'], []) as $relation) {
            $defs = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $relation], []);

            if (is_array($defs) && !empty($defs['entity']) && !empty($defs['foreign'])) {
                $data = new \stdClass();
                $data->modifiedAt = $entity->get('modifiedAt');
                $data->_skipIsEntityUpdated = true;

                $params = [
                    'where' => [
                        [
                            'type'      => 'linkedWith',
                            'attribute' => $defs['foreign'],
                            'value'     => [$entity->id]
                        ]
                    ]
                ];

                $this->getInjection('container')->get('pseudoTransactionManager')->pushMassUpdateEntityJob($defs['entity'], $data, $params);
            }
        }
    }

    public function save(Entity $entity, array $options = [])
    {
        $nowString = date('Y-m-d H:i:s');
        $user = $this->getEntityManager()->getUser();

        if ($entity->isNew()) {
            if (!$entity->has('id')) {
                $entity->set('id', Util::generateId());
            } else {
                // delete deleted record
                $qb = $this->getConnection()->createQueryBuilder()
                    ->delete($this->getConnection()->quoteIdentifier($this->getMapper()->toDb($entity->getEntityType())))
                    ->where('id = :id')
                    ->andWhere('deleted = :true')
                    ->setParameter('id', $entity->id)
                    ->setParameter('true', true, ParameterType::BOOLEAN);
                try {
                    $qb->executeQuery();
                } catch (\Throwable $e) {
                }
            }
            if ($entity->hasAttribute('createdAt') && empty($entity->get('createdAt'))) {
                $entity->set('createdAt', $nowString);
            }
            if ($entity->hasAttribute('createdById') && $user) {
                $entity->set('createdById', $user->get('id'));
            }
        }

        if ($entity->hasAttribute('modifiedAt') && ($entity->isNew() ? empty($entity->get('modifiedAt')) : !$entity->isAttributeChanged('modifiedAt'))) {
            $entity->set('modifiedAt', $nowString);
        }

        if ($entity->hasAttribute('modifiedById') && $user) {
            $entity->set('modifiedById', $user->get('id'));
            $entity->set('modifiedByName', $user->get('name'));
        }

        return parent::save($entity, $options);
    }

    protected function getFieldByTypeList($type)
    {
        return $this->getFieldManagerUtil()->getFieldByTypeList($this->entityType, $type);
    }

    protected function processFileFieldsSave(Entity $entity)
    {
        foreach ($entity->getRelations() as $name => $defs) {
            if (!isset($defs['type']) || !isset($defs['entity'])) {
                continue;
            }
            if (!($defs['type'] === $entity::BELONGS_TO && $defs['entity'] === 'Attachment')) {
                continue;
            }

            $attribute = $name . 'Id';
            if (!$entity->hasAttribute($attribute)) {
                continue;
            }
            if (!$entity->get($attribute)) {
                continue;
            }
            if (!$entity->isAttributeChanged($attribute)) {
                continue;
            }

            $attachment = $this->getEntityManager()->getEntity('Attachment', $entity->get($attribute));
            if (!$attachment || !empty($attachment->get('relatedId'))) {
                continue;
            }
            $attachment->set(array(
                'relatedId'   => $entity->id,
                'relatedType' => $entity->getEntityType()
            ));
            $this->getEntityManager()->saveEntity($attachment);
        }
    }

    protected function processWysiwygFieldsSave(Entity $entity)
    {
        if (!$entity->isNew()) {
            return;
        }

        $fieldsDefs = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], []);
        foreach ($fieldsDefs as $field => $defs) {
            if (!empty($defs['type']) && $defs['type'] === 'wysiwyg') {
                $content = $entity->get($field);
                if (!$content) {
                    continue;
                }
                if (preg_match_all("/\?entryPoint=download&amp;id=([^&=\"']+)/", $content, $matches)) {
                    if (!empty($matches[1]) && is_array($matches[1])) {
                        foreach ($matches[1] as $id) {
                            $attachment = $this->getEntityManager()->getEntity('Attachment', $id);
                            if ($attachment) {
                                if (!$attachment->get('relatedId')) {
                                    $attachment->set([
                                        'relatedId'   => $entity->id,
                                        'relatedType' => $entity->getEntityType()
                                    ]);
                                    $this->getEntityManager()->saveEntity($attachment);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function processSpecifiedRelationsSave(Entity $entity, array $options = array())
    {
        $relationTypeList = [$entity::HAS_MANY, $entity::MANY_MANY, $entity::HAS_CHILDREN];
        foreach ($entity->getRelations() as $name => $defs) {
            if (in_array($defs['type'], $relationTypeList)) {
                $fieldName = $name . 'Ids';
                $columnsFieldsName = $name . 'Columns';


                if ($entity->has($fieldName) || $entity->has($columnsFieldsName)) {
                    if ($this->getMetadata()->get("entityDefs." . $entity->getEntityType() . ".fields.{$name}.noSave")) {
                        continue;
                    }

                    if ($entity->has($fieldName)) {
                        $specifiedIds = $entity->get($fieldName) ?? [];
                    } else {
                        $specifiedIds = [];
                        foreach ($entity->get($columnsFieldsName) as $id => $d) {
                            $specifiedIds[] = $id;
                        }
                    }
                    if (is_array($specifiedIds)) {
                        $toRemoveIds = [];
                        $existingIds = [];
                        $toUpdateIds = [];
                        $existingColumnsData = new \stdClass();

                        $defs = [];
                        $columns = $this->getMetadata()->get("entityDefs." . $entity->getEntityType() . ".fields.{$name}.columns");
                        if (!empty($columns)) {
                            $columnData = $entity->get($columnsFieldsName);
                            $defs['additionalColumns'] = $columns;
                        }

                        $foreignCollection = $entity->get($name, $defs);
                        if ($foreignCollection) {
                            foreach ($foreignCollection as $foreignEntity) {
                                $existingIds[] = $foreignEntity->id;
                                if (!empty($columns)) {
                                    $data = new \stdClass();
                                    foreach ($columns as $columnName => $columnField) {
                                        $foreignId = $foreignEntity->id;
                                        $data->$columnName = $foreignEntity->get($columnField);
                                    }
                                    $existingColumnsData->$foreignId = $data;
                                    if (!$entity->isNew()) {
                                        $entity->setFetched($columnsFieldsName, $existingColumnsData);
                                    }
                                }

                            }
                        }

                        if (!$entity->isNew()) {
                            if ($entity->has($fieldName)) {
                                $entity->setFetched($fieldName, $existingIds);
                            }
                            if ($entity->has($columnsFieldsName) && !empty($columns)) {
                                $entity->setFetched($columnsFieldsName, $existingColumnsData);
                            }
                        }

                        foreach ($existingIds as $id) {
                            if (!in_array($id, $specifiedIds)) {
                                $toRemoveIds[] = $id;
                            } else {
                                if (!empty($columns)) {
                                    foreach ($columns as $columnName => $columnField) {
                                        if (isset($columnData->$id) && is_object($columnData->$id)) {
                                            if (
                                                property_exists($columnData->$id, $columnName)
                                                && (
                                                    !property_exists($existingColumnsData->$id, $columnName)
                                                    || $columnData->$id->$columnName !== $existingColumnsData->$id->$columnName
                                                )
                                            ) {
                                                $toUpdateIds[] = $id;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        foreach ($toRemoveIds as $id) {
                            $this->unrelate($entity, $name, $id, $options);
                        }

                        foreach ($specifiedIds as $id) {
                            if (!in_array($id, $existingIds)) {
                                $data = null;
                                if (!empty($columns) && isset($columnData->$id)) {
                                    $data = $columnData->$id;
                                }
                                if ($name === 'teams') {
                                    $data = ['entityType' => $entity->getEntityType()];
                                }
                                try {
                                    $this->relate($entity, $name, $id, $data, $options);
                                } catch (NotUnique $e) {
                                }
                            }
                        }

                        if (!empty($columns)) {
                            foreach ($toUpdateIds as $id) {
                                $data = $columnData->$id;
                                $this->updateRelation($entity, $name, $id, $data);
                            }
                        }
                    }
                }
            } else {
                if ($defs['type'] === $entity::HAS_ONE) {
                    if (empty($defs['entity']) || empty($defs['foreignKey'])) {
                        continue;
                    }

                    if ($this->getMetadata()->get("entityDefs." . $entity->getEntityType() . ".fields.{$name}.noSave")) {
                        continue;
                    }

                    $foreignEntityType = $defs['entity'];
                    $foreignKey = $defs['foreignKey'];
                    $idFieldName = $name . 'Id';
                    $nameFieldName = $name . 'Name';

                    if (!$entity->has($idFieldName)) {
                        continue;
                    }

                    $where = [];
                    $where[$foreignKey] = $entity->id;
                    $previousForeignEntity = $this->getEntityManager()->getRepository($foreignEntityType)->where($where)->findOne();
                    if ($previousForeignEntity) {
                        if (!$entity->isNew()) {
                            $entity->setFetched($idFieldName, $previousForeignEntity->id);
                        }
                        if ($previousForeignEntity->id !== $entity->get($idFieldName)) {
                            $previousForeignEntity->set($foreignKey, null);
                            $this->getEntityManager()->saveEntity($previousForeignEntity);
                        }
                    } else {
                        if (!$entity->isNew()) {
                            $entity->setFetched($idFieldName, null);
                        }
                    }

                    if ($entity->get($idFieldName)) {
                        $newForeignEntity = $this->getEntityManager()->getEntity($foreignEntityType, $entity->get($idFieldName));
                        if ($newForeignEntity) {
                            $newForeignEntity->set($foreignKey, $entity->id);
                            $this->getEntityManager()->saveEntity($newForeignEntity);
                        } else {
                            $entity->set($idFieldName, null);
                        }
                    }
                }
            }
        }
    }

    /**
     * @deprecated
     */
    protected function assignmentNotifications(Entity $entity): void
    {
    }

    /**
     * @deprecated
     */
    protected function createOwnNotification(Entity $entity, ?string $userId): void
    {
    }

    /**
     * @deprecated
     */
    protected function createAssignmentNotification(Entity $entity, ?string $userId): void
    {
    }

    /**
     * @deprecated
     */
    protected function getOwnNotificationMessageData(Entity $entity): array
    {
        return [];
    }

    /**
     * @deprecated
     */
    protected function getAssignmentNotificationMessageData(Entity $entity): array
    {
        return $this->getOwnNotificationMessageData($entity);
    }

    /**
     * Dispatch an event
     *
     * @param string $action
     * @param Entity $entity
     * @param array  $options
     * @param mixed  $arg1
     * @param mixed  $arg2
     * @param mixed  $arg3
     */
    private function dispatch(string $action, Entity $entity, $options, $arg1 = null, $arg2 = null, $arg3 = null)
    {
        $event = new Event(
            [
                'entityType'     => $this->entityType,
                'entity'         => $entity,
                'options'        => $options,
                'relationName'   => $arg1,
                'relationParams' => $arg2,
                'relationData'   => $arg2,
                'foreign'        => $arg3,
            ]
        );

        // dispatch an event
        $this->getInjection('eventManager')->dispatch('Entity', $action, $event);
    }

    public function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->getInjection('connection');
    }

    protected function beforeRestore($id)
    {
    }

    protected function afterRestore($entity)
    {
    }
}
