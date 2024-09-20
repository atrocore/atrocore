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

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

class ExtensibleEnumOption extends Base
{
    protected array $cachedOptions = [];

    public function getPreparedOption(string $extensibleEnumId, ?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        $options = $this->getPreparedOptions($extensibleEnumId, [$id]);

        return $options[0] ?? null;
    }

    public function getPreparedOptions(string $extensibleEnumId, ?array $ids): ?array
    {
        if (!is_array($ids)) {
            return null;
        }

        $res = [];
        foreach ($ids as $id) {
            $id = (string)$id;
            if ($id === '') {
                continue;
            }

            if (!isset($this->cachedOptions[$id])) {
                // prepare select
                $select = ['eeo.id', 'eeo.code', 'eeo.color', 'eeo.name', 'eeo.sort_order', 'eeeeo.sorting'];
                foreach ($this->getLingualFields('name') as $lingualField) {
                    $select[] = 'eeo.' . Util::toUnderScore($lingualField);
                }
                if ($this->getMetadata()->get(['entityDefs', 'ExtensibleEnumOption', 'fields', 'description'])) {
                    $select[] = 'eeo.description';
                }
                $select[] = 'ee.multilingual AS multilingual';

                $records = $this
                    ->getConnection()
                    ->createQueryBuilder()
                    ->select(implode(',', $select))
                    ->from('extensible_enum_option', 'eeo')
                    ->innerjoin('eeo', 'extensible_enum_extensible_enum_option', 'eeeeo', 'eeeeo.extensible_enum_option_id = eeo.id AND eeeeo.deleted = :false')
                    ->innerjoin('eeeeo', 'extensible_enum', 'ee', 'ee.id = eeeeo.extensible_enum_id AND ee.deleted = :false')
                    ->where('eeo.deleted = :false')
                    ->andWhere('ee.id = :id')
                    ->setParameter('false', false, Mapper::getParameterType(false))
                    ->setParameter('id', $extensibleEnumId, Mapper::getParameterType($extensibleEnumId))
                    ->orderBy('eeeeo.sorting', 'ASC')
                    ->fetchAllAssociative();

                foreach ($records as $item) {
                    $row = Util::arrayKeysToCamelCase($item);
                    $row['preparedName'] = !empty($row['multilingual']) ? $row[$this->getOptionName()] : $row['name'];
                    $this->cachedOptions[$row['id']] = $row;

                    if ($id == $row['id']) {
                        $res[] = $this->cachedOptions[$row['id']];
                    }
                }
            } else {
                $res[] = $this->cachedOptions[$id];
            }
        }

        if (count($res) > 1) {
            usort($res, function ($option1, $option2) {
                return ($option1['sorting'] < $option2['sorting']) ? -1 : 1;
            });
        }
        return $res;
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if ($entity->get('name') === null && $entity->get('code') !== null) {
            $entity->set('name', $entity->get('code'));
        }

        if ($entity->isNew() && $entity->get('sortOrder') === null) {
            $entity->set('sortOrder', time() - (new \DateTime('2023-01-01'))->getTimestamp());
        }

        parent::beforeSave($entity, $options);
    }

    public function updateSortOrder(array $ids): void
    {
        $collection = $this->where(['id' => $ids])->find();
        if (empty($collection[0])) {
            return;
        }

        foreach ($ids as $k => $id) {
            $sortOrder = (int)$k * 10;
            foreach ($collection as $entity) {
                if ($entity->get('id') !== (string)$id) {
                    continue;
                }
                $entity->set('sortOrder', $sortOrder);
                $this->save($entity);
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $this->validateSystemOptions($entity);
        $this->validateOptionsBeforeRemove($entity);

        parent::beforeRemove($entity, $options);
    }

    public function validateSystemOptions(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['app', 'extensibleEnumOptions']) as $v) {
            if ($entity->get('id') === $v['id']) {
                throw new BadRequest(sprintf($this->getLanguage()->translate('extensibleEnumOptionIsSystem', 'exceptions', 'ExtensibleEnumOption'), $entity->get('name')));
            }
        }
    }

    public function validateOptionsBeforeRemove(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs']) as $entityName => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDef) {
                foreach ($entity->get('extensibleEnums') as $extensibleEnum) {
                    if (empty($fieldDef['notStorable']) && !empty($fieldDef['extensibleEnumId']) && $fieldDef['extensibleEnumId'] === $extensibleEnum->get('id')) {
                        $column = Util::toUnderScore($field);

                        $qb = $this->getConnection()->createQueryBuilder();
                        $qb->select('t.*')
                            ->from($this->getConnection()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName))), 't');
                        if ($fieldDef['type'] === 'extensibleEnum') {
                            $qb->where("t.$column = :itemId")
                                ->setParameter('itemId', $entity->get('id'));
                        } else {
                            $qb->where("t.$column LIKE :itemId")
                                ->setParameter('itemId', "%\"{$entity->get('id')}\"%");
                        }
                        $qb->andWhere('t.deleted = :false')
                            ->setParameter('false', false, ParameterType::BOOLEAN);

                        $record = $qb->fetchAssociative();

                        if (!empty($record)) {
                            throw new BadRequest(
                                sprintf(
                                    $this->getLanguage()->translate('extensibleEnumOptionIsUsed', 'exceptions', 'ExtensibleEnumOption'),
                                    $entity->get('name'),
                                    $this->getLanguage()->translate($field, 'fields', $entity->getEntityType()),
                                    $entityName,
                                    $record['name'] ?? ''
                                )
                            );
                        }
                    }

                }
            }
        }
    }

    public function getLingualFields(string $fieldName = 'name'): array
    {
        $names = [];
        foreach ($this->getMetadata()->get(['entityDefs', 'ExtensibleEnumOption', 'fields']) as $field => $fieldDefs) {
            if (!empty($fieldDefs['multilangField']) && $fieldDefs['multilangField'] === $fieldName) {
                $names[] = $field;
            }
        }

        return $names;
    }

    protected function getOptionName(): string
    {
        $language = \Espo\Core\Services\Base::getLanguagePrism();
        if (empty($language)) {
            $language = $this->getInjection('container')
                ->get('preferences')
                ->get('language');
        }
        if (!empty($language) && $language !== 'main') {
            if ($this->getConfig()->get('isMultilangActive') && in_array($language, $this->getConfig()->get('inputLanguageList', []))) {
                return Util::toCamelCase('name_' . strtolower($language));
            }
        }

        return 'name';
    }

}
