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

namespace Atro\Core\SelectManagers;

use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\IdGenerator;
use Atro\ORM\DB\RDB\Mapper;
use Atro\ORM\DB\RDB\Query\QueryConverter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Acl;
use Espo\Core\AclManager;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Utils\Language;
use Espo\Core\ORM\Entity;
use Atro\Core\SelectManagerFactory;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\Entities\User;
use Espo\ORM\EntityManager;
use Espo\ORM\IEntity;
use Espo\ORM\Repository;
use Psr\Container\ContainerInterface;

class Base
{
    public const MIN_LENGTH_FOR_CONTENT_SEARCH = 4;

    protected array $selectParameters;

    protected ?IEntity $seed = null;

    protected array $textFilterUseContainsAttributeList = [];

    protected array $actionExecutionFilter = [];

    protected array $cachedData = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $entityType
    ) {
    }

    protected function limit(?int $offset, ?int $maxSize, array &$result): void
    {
        if ($offset !== null) {
            $result['offset'] = $offset;
        }
        if ($maxSize !== null) {
            $result['limit'] = $maxSize;
        }
    }

    protected function order(string $sortBy, bool $desc, array &$result): void
    {
        $direction = $desc ? 'DESC' : 'ASC';

        if ($sortBy !== '') {
            $type = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $sortBy, 'type']);

            if ($type === 'address') {
                $result['orderBy'] = [[$sortBy . 'Country', $direction], [$sortBy . 'City', $direction], [$sortBy . 'Street', $direction]];
                return;
            }

            $result['orderBy'] = match (true) {
                in_array($type, ['link', 'file', 'image']) => $sortBy . 'Name',
                $type === 'linkParent'                     => $sortBy . 'Type',
                default                                    => $sortBy,
            };
        }

        $result['order'] = $direction;
    }

    protected function getTextFilterFieldList(): array
    {
        $result = ['id'];

        $fields = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []);
        $textFilterFields = $this->getMetadata()->get(['scopes', $this->entityType, 'textFilterFields'], []);
        foreach ($fields as $field => $fieldDefs) {
            if (!empty($fieldDefs['notStorable']) || !empty($fieldDefs['filterDisabled']) || !empty($fieldDefs['attributeId'])) {
                continue;
            }

            if (!empty($textFilterFields)) {
                if (in_array($field, $textFilterFields)) {
                    $result[] = $field;
                }
                continue;
            }

            if (in_array($fieldDefs['type'], ['varchar', 'text', 'markdown', 'email', 'url', 'wysiwyg', 'int', 'float', 'autoincrement'])) {
                $result[] = $field;
            }
        }

        return $result;
    }

    protected function getSeed(): IEntity
    {
        if (empty($this->seed)) {
            $this->seed = $this->getEntityManager()->getEntityFactory()->create($this->entityType);
            $this->seed->setIsNew(true);
        }

        return $this->seed;
    }

    public function applyWhere(array $where, array &$result): void
    {
        $this->prepareResult($result);
        $this->where($where, $result);
    }

    protected function where(array $where, array &$result): void
    {
        $this->prepareResult($result);

        foreach ($where as $item) {
            $type = $item['type'] ?? null;
            $value = $item['value'] ?? null;

            if ($type === 'bool' && !empty($value) && is_array($value)) {
                foreach ($value as $filter) {
                    $this->applyBoolFilter($filter, $result);
                }
            } elseif ($type === 'textFilter' && $value !== null && $value !== '') {
                $this->textFilter($value, $result);
            } elseif ($type === 'primary' && !empty($value)) {
                $this->applyPrimaryFilter($value, $result);
            }
        }

        $result['whereClause'] = array_merge($result['whereClause'], $this->convertWhere($where, $result));
    }

    /**
     * Converts the where items into the where clause; the bool and primary filters are applied by where().
     */
    public function convertWhere(array $where, ?array &$result = null): array
    {
        $whereClause = [];

        foreach ($where as $item) {
            if (!isset($item['type']) || in_array($item['type'], ['bool', 'primary'])) {
                continue;
            }

            $part = $this->getWherePart($item, $result);
            if (!empty($part)) {
                $whereClause[] = $part;
            }
        }

        return $whereClause;
    }

    public function mutateWhereQuery(array &$where, &$result = null): void
    {
        foreach ($where as $key => &$item) {
            if (!empty($item['data']['disabled'])) {
                unset($where[$key]);
                continue;
            }

            if (isset($item['rules'])) {
                $this->mutateWhereQuery($item['rules'], $result);
                $item = ['type' => $this->qbConditionToType((string)$item['condition']), 'value' => $item['rules']];
            } else {
                // a module may turn a condition of its own into a ready one, which the core does not process then
                $event = $this->dispatch('SelectManager', 'mutateWhereItem', new Event([
                    'item'          => $item,
                    'entityType'    => $this->entityType,
                    'selectManager' => $this,
                    'handled'       => false,
                ]));
                if ($event->getArgument('handled')) {
                    $item = $event->getArgument('item');
                    continue;
                }

                // for attributes
                $additionForAttribute = [];
                $attribute            = "";
                if (!empty($item['id'])) {
                    $attribute = $item['id'];
                    if (str_contains($item['id'], 'attr_')) {
                        $parts                               = explode('_', $item['id']);
                        $additionForAttribute['attribute']   = $result['attributesIds'][] = $parts[1];
                        $additionForAttribute['isAttribute'] = true;

                        if (!empty($parts[2])) {
                            if ($parts[2] === 'main') {
                                $additionForAttribute['language'] = 'main';
                            } else {
                                $language = Util::toUnderScore($parts[2]);
                                $language = explode('_', $language);
                                if (count($language) === 2) {
                                    $language                         = $language[0] . '_' . strtoupper($language[1]);
                                    $additionForAttribute['language'] = $language;
                                }
                            }
                        }
                    } else if (!$this->isKnownField($attribute)) {
                        throw new BadRequest(sprintf($this->getLanguage()->translate('filterContainsUnknownField', 'exceptions', 'Global'), $attribute));
                    }
                }

                if (!empty($item['data']['subQuery']) && !empty($item['operator']) && !empty($item['id'])) {
                    $value = [];
                    $type  = $this->qbOperatorToType((string)$item['operator']);
                    if (!empty($item['value'])) {
                        $value = $item['value'];
                    }
                    $subQuery = $item['data']['subQuery'];

                    if (!empty($subQuery['rules'])) {
                        $this->mutateWhereQuery($subQuery['rules'], $result);
                    }

                    $item = array_merge([
                        'attribute' => $item['id'],
                        'type'      => $this->qbOperatorToType((string)$item['operator']),
                        'subQuery'  => $subQuery,
                    ], $additionForAttribute);

                    if (!empty($value)) {
                        $condition = in_array($item['type'], ['linkedWith', 'in']) ? 'or' : 'and';
                        $item      = [
                            'type'  => $condition,
                            'value' => [
                                $item,
                                array_merge([
                                    'attribute' => $attribute,
                                    'type'      => $item['type'],
                                    'value'     => $value,
                                ], $additionForAttribute)
                            ]
                        ];
                    }

                    if ($type === 'notIn') {
                        $item = [
                            'type'  => 'or',
                            'value' => [
                                $item,
                                array_merge([
                                    'type'      => 'isNull',
                                    'attribute' => $attribute,
                                ], $additionForAttribute)
                            ]
                        ];
                    }
                } else if (isset($item['id']) && !empty($item['operator'])) {
                    if (!empty($item['data']['operatorType'])) {
                        $type = $item['data']['operatorType'];
                    } else {
                        $type = $this->qbOperatorToType((string)$item['operator']);
                    }

                    if (!empty($item['type']) && $item['type'] === 'boolean' && in_array($item['operator'], ['equal', 'not_equal'])) {
                        if (is_string($item['value'])) {
                            $boolValue = strtolower($item['value']) === 'true';
                        } else {
                            $boolValue = !empty($item['value']);
                        }

                        $isTrueCondition = ($boolValue && $item['operator'] === 'equal') || (!$boolValue && $item['operator'] === 'not_equal');
                        $item            = [
                            'attribute' => $attribute,
                            'type'      => $isTrueCondition ? 'isTrue' : 'isFalse',
                        ];
                    } else if (in_array($type, ['is_me', 'is_not_me', 'is_team_member', 'include_me', 'exclude_me', 'is_my_team', 'is_not_my_team'])) {
                        $seed         = $this->getSeed();
                        $link         = substr($item['id'], -2) === 'Id' ? substr($item['id'], 0, -2) : $item['id'];
                        $entity       = $seed->getRelationParam($link, 'entity');
                        $relationType = $seed->getRelationType($link);
                        if ($entity === 'User') {
                            if ($relationType === 'belongsTo') {
                                switch ($type) {
                                    case 'is_me':
                                        $item = [
                                            "attribute" => $item['id'],
                                            "type"      => "equals",
                                            "value"     => $this->getUser()->id
                                        ];
                                        break;
                                    case 'is_not_me':
                                        $item = [
                                            "attribute" => $item['id'],
                                            "type"      => "notEquals",
                                            "value"     => $this->getUser()->id
                                        ];
                                        break;
                                    case 'is_team_member':
                                        $item = [
                                            "attribute" => $item['id'],
                                            "type"      => "in",
                                            "subQuery"  => [
                                                [
                                                    "attribute" => "teams",
                                                    "type"      => "linkedWith",
                                                    "value"     => $this->getUser()->getTeamIdList()
                                                ]
                                            ]
                                        ];
                                        break;
                                }
                            } else {
                                switch ($type) {
                                    case 'include_me':
                                        $item = [
                                            "attribute" => $item['id'],
                                            "type"      => "linkedWith",
                                            "value"     => [$this->getUser()->id]
                                        ];
                                        break;
                                    case 'exclude_me':
                                        $item = [
                                            "attribute" => $item['id'],
                                            "type"      => "notLinkedWith",
                                            "value"     => [$this->getUser()->id]
                                        ];
                                        break;
                                    case 'is_team_member':
                                        $item = [
                                            "attribute" => $item['id'],
                                            "type"      => "linkedWith",
                                            "subQuery"  => [
                                                [
                                                    "attribute" => "teams",
                                                    "type"      => "linkedWith",
                                                    "value"     => $this->getUser()->getTeamIdList()
                                                ]
                                            ]
                                        ];
                                        break;
                                }
                            }
                        } else if ($entity === 'Team' && $relationType == 'belongsTo') {
                            switch ($type) {
                                case 'is_my_team':
                                    $item = [
                                        "attribute" => $item['id'],
                                        "type"      => "in",
                                        "value"     => $this->getUser()->getTeamIdList()
                                    ];
                                    break;
                                case 'is_not_my_team':
                                    $item = [
                                        'type'  => 'or',
                                        'value' => [
                                            [
                                                "attribute" => $item['id'],
                                                "type"      => "notIn",
                                                "value"     => $this->getUser()->getTeamIdList()
                                            ],
                                            [
                                                "attribute" => $item['id'],
                                                "type"      => "isNull",
                                            ]

                                        ]
                                    ];
                                    break;
                            }
                        } else {
                            $item = [];
                        }
                    } else {
                        $isUnitField = !empty($item['data']['combinedField']);
                        $item        = [
                            'attribute' => $attribute,
                            'type'      => $type,
                            'value'     => $item['value'],
                        ];
                        if ($isUnitField) {
                            if (!empty($additionForAttribute['isAttribute'])) {
                                $item['combinedField'] = true;
                            } else {
                                $fieldDefs = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $attribute]);
                                if (!empty($fieldDefs['mainField']) && !empty($fieldDefs['measureId'])) {
                                    $item['combinedField'] = true;
                                    $item['attribute']     = $fieldDefs['mainField'];
                                }
                            }
                        }
                    }

                    $item = array_merge($item, $additionForAttribute);

                    if ($type === 'notIn') {
                        $item = [
                            "type"  => "or",
                            "value" => [
                                $item,
                                array_merge([
                                    "type"      => "isNull",
                                    "attribute" => $attribute,
                                ], $additionForAttribute)
                            ]
                        ];
                    }
                }
            }
        }
    }

    protected function isKnownField(string $attribute): bool
    {
        if ($attribute === 'id') {
            return true;
        }
        if ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $attribute])) {
            return true;
        }
        if (str_ends_with($attribute, 'Id')) {
            $baseField = substr($attribute, 0, -2);
            if ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $baseField])) {
                return true;
            }
        }
        return false;
    }

    public function qbConditionToType(string $condition): string
    {
        return strtolower($condition) === 'or' ? 'or' : 'and';
    }

    public function qbOperatorToType(string $operator): string
    {
        return match ($operator) {
            'equal'                                         => 'equals',
            'not_equal'                                     => 'notEquals',
            'begins_with'                                   => 'startsWith',
            'ends_with'                                     => 'endsWith',
            'not_contains'                                  => 'notContains',
            'less'                                          => 'lessThan',
            'less_or_equal'                                 => 'lessThanOrEquals',
            'greater'                                       => 'greaterThan',
            'greater_or_equal'                              => 'greaterThanOrEquals',
            'not_in'                                        => 'notIn',
            'linked_with'                                   => 'linkedWith',
            'not_linked_with'                               => 'notLinkedWith',
            'is_attribute_linked', 'is_linked'              => 'isLinked',
            'is_attribute_not_linked', 'is_not_linked'      => 'isNotLinked',
            'is_null'                                       => 'isNull',
            'is_not_null'                                   => 'isNotNull',
            'array_any_of'                                  => 'arrayAnyOf',
            'array_none_of'                                 => 'arrayNoneOf',
            'last_x_days'                                   => 'lastXDays',
            'older_than_x_days'                             => 'olderThanXDays',
            'after_x_days'                                  => 'afterXDays',
            'next_x_days'                                   => 'nextXDays',
            'current_month'                                 => 'currentMonth',
            'last_month'                                    => 'lastMonth',
            'next_month'                                    => 'nextMonth',
            'current_year'                                  => 'currentYear',
            'last_year'                                     => 'lastYear',
            'word_similar'                                  => 'wordSimilar',
            default                                         => $operator,
        };
    }

    protected function q(array $params, array &$result): void
    {
        if (isset($params['q']) && $params['q'] !== '') {
            $this->textFilter($params['q'], $result);
        }
    }

    public function manageAccess(array &$result): void
    {
        $this->prepareResult($result);
        $this->applyAccess($result);
    }

    protected function prepareResult(array &$result): void
    {
        $result['joins'] ??= [];
        $result['leftJoins'] ??= [];
        $result['whereClause'] ??= [];
        $result['customJoin'] ??= '';
        $result['additionalSelectColumns'] ??= [];
        $result['joinConditions'] ??= [];
        $result['customWhere'] ??= '';
    }

    protected function access(array &$result): void
    {
        $entityType = $this->getEntityType();

        if (!$this->getUser()->isAdmin() && $this->getMetadata()->get(['scopes', $entityType, 'type'], 'Base') === 'Relation') {
            $result['callbacks'][] = [$this, 'accessForRelationEntity'];
        }

        if ($this->getAcl()->checkReadOnlyOwn($entityType)) {
            $this->accessOnlyOwn($result);
        } elseif (!$this->getUser()->isAdmin()) {
            if ($this->getAcl()->checkReadOnlyTeam($entityType)) {
                $this->accessOnlyTeam($result);
            } elseif ($this->getAcl()->checkReadNo($entityType)) {
                $this->accessNo($result);
            }
        }
    }

    protected function accessNo(array &$result): void
    {
        $result['whereClause'][] = ['id' => null];
    }

    public function accessForRelationEntity(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields']) as $field => $fieldDefs) {
            if (array_key_exists('relationField', $fieldDefs)) {
                $entityName = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links', $field, 'entity']);
                if (empty($entityName)) {
                    continue;
                }

                if (!$this->getAcl()->checkReadOnlyOwn($entityName) && !$this->getAcl()->checkReadOnlyTeam($entityName)) {
                    continue;
                }

                $sp           = $this->createSelectManager($entityName)->getSelectParams([], true, true);
                $sp['select'] = ['id'];

                $qb1 = $mapper->createSelectQueryBuilder($this->getEntityManager()->getRepository($entityName)->get(), $sp);
                $qb1->select("{$tableAlias}.id");

                $column = $mapper->getQueryConverter()->toDb("{$field}Id");

                $qb->andWhere("{$tableAlias}.{$column} IN ({$qb1->getSql()})");
                foreach ($qb1->getParameters() as $param => $val) {
                    $qb->setParameter($param, $val, Mapper::getParameterType($val));
                }
            }
        }
    }

    protected function accessOnlyOwn(array &$result): void
    {
        $userId = $this->getUser()->id;

        $where = [];
        if ($this->hasOwnerUserField()) {
            $where['ownerUserId'] = $userId;
        }
        if ($this->hasAssignedUserField()) {
            $where['assignedUserId'] = $userId;
        }
        if ($this->hasCreatedByField() && !$this->hasAssignedUserField() && !$this->hasOwnerUserField()) {
            $where['createdById'] = $userId;
        }

        $result['whereClause'][] = ['OR' => $where];
    }

    protected function accessOnlyTeam(array &$result): void
    {
        $result['callbacks'][] = [$this, 'applyAccessOnlyTeam'];
    }

    public function applyAccessOnlyTeam(QueryBuilder $qb, IEntity $entity, array $params, Mapper $mapper): void
    {
        $currentUserId = $this->getUser()->id;

        $ta = $mapper->getQueryConverter()->getMainTableAlias();

        $entityTypeParam = 'entity_type_' . IdGenerator::unsortableId();

        $parts = [];
        if ($this->hasTeamsField()) {
            $parts[] = "($ta.id IN (SELECT entity_id FROM entity_team WHERE deleted=:false AND entity_type=:$entityTypeParam AND team_id IN (:teamsIds)))";
            $qb->setParameter('teamsIds', $this->getUser()->getLinkMultipleIdList('teams'), Connection::PARAM_STR_ARRAY);
            $qb->setParameter($entityTypeParam, $this->entityType);
            $qb->setParameter('false', false, ParameterType::BOOLEAN);
        }

        if ($this->hasOwnerUserField()) {
            $parts[] = "($ta.owner_user_id = :currentUserId)";
            $qb->setParameter('currentUserId', $currentUserId);
        }

        if ($this->hasAssignedUserField()) {
            $parts[] = "($ta.assigned_user_id = :currentUserId)";
            $qb->setParameter('currentUserId', $currentUserId);
        }

        if ($this->hasCreatedByField() && !$this->hasAssignedUserField() && !$this->hasOwnerUserField()) {
            $parts[] = "($ta.created_by_id = :currentUserId)";
            $qb->setParameter('currentUserId', $currentUserId);
        }

        if (!empty($parts)) {
            $qb->andWhere(implode(' OR ', $parts));
        }
    }

    protected function hasOwnerUserField(): bool
    {
        return !empty($this->getMetadata()->get(['scopes', $this->getEntityType(), 'hasOwner']));
    }

    protected function hasAssignedUserField(): bool
    {
        return !empty($this->getMetadata()->get(['scopes', $this->getEntityType(), 'hasAssignedUser']));
    }

    protected function boolFilterFieldsFilter(array &$result): void
    {
        foreach ($this->getBoolFilterParameter('fieldsFilter') ?? [] as $field => $value) {
            $result['whereClause'][] = [$field => $value];
        }
    }

    protected function boolFilterOnlyActive(array &$result): void
    {
        $result['whereClause'][] = ['isActive' => true];
    }

    protected function boolFilterContainsPersonalData(array &$result): void
    {
        if ($this->getMetadata()->get(['scopes', $this->getEntityType(), 'type']) === 'ReferenceData') {
            throw new Error("'ReferenceData' type does not have personal data table");
        }

        $result['callbacks'][] = [$this, 'applyBoolFilterContainsPersonalData'];
    }

    public function applyBoolFilterContainsPersonalData(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $repository = $this->getEntityManager()->getRepository($this->getEntityType());

        if (!$repository->hasPersonalDataTable()) {
            throw new Error("Entity '{$this->getEntityType()}' does not have personal data table");
        }

        $ta = $mapper->getQueryConverter()->getMainTableAlias();
        $tableName = $mapper->getQueryConverter()->quoteIdentifier($repository->getPersonalDataTableName());

        $qb->andWhere("$ta.id IN (SELECT pd.record_id FROM $tableName pd)");
    }

    protected function boolFilterErased(array &$result): void
    {
        if (!$this->getUser()->isAdmin() && !$this->getUser()->isDataPrivacyAdmin()) {
            throw new Forbidden();
        }

        if (empty($this->getMetadata()->get(['scopes', $this->getEntityType(), 'containsPersonalData'])) || $this->getMetadata()->get(['scopes', $this->getEntityType(), 'type']) === 'ReferenceData') {
            throw new Error("Entity '{$this->getEntityType()}' does not contain personal data");
        }

        $result['whereClause'][] = [
            'erasedAt!=' => null
        ];
    }

    protected function boolFilterOnlyArchived(array &$result): void
    {
        $result['whereClause'][] = ['isArchived' => true];
    }

    protected function boolFilterWithArchived(array &$result): void
    {
        $result['withArchived'] = true;
    }

    protected function boolFilterOnlyDeleted(array &$result): void
    {
        $result['withDeleted'] = true;
        $result['whereClause'][] = ['deleted' => true];
        $result['additionalSelectColumns'][QueryConverter::TABLE_ALIAS . '.deleted'] = 'deleted';
    }

    protected function boolFilterNotDisabledOptions(array &$result): void
    {
        $ids = $this->getBoolFilterParameter('notDisabledOptions');
        if (!empty($ids)) {
            $result['whereClause'][] = ['id!=' => $ids];
        }
    }

    protected function boolFilterNotActive(array &$result): void
    {
        $result['whereClause'][] = ['isActive' => false];
    }

    protected function boolFilterOnlyBookmarked(array &$result): void
    {
        $result['callbacks'][] = [$this, 'applyOnlyBookmarked'];
    }

    protected function boolFilterMultipleClassifications(array &$result): void
    {
        $result['callbacks'][] = [$this, 'applyMultipleClassifications'];
    }

    protected function boolFilterReportSpecific(array &$result): void
    {
        $data = $this->getBoolFilterParameter('reportSpecific') ?? [];

        if (empty($data['reportData']['type']) || empty($data['field'])) {
            return;
        }

        $reportHandlerClass = $this->getMetadata()->get("app.ReportDashletType.{$data['reportData']['type']}.className");
        if (empty($reportHandlerClass)) {
            return;
        }

        $handler = $this->container->get($reportHandlerClass);

        $methodName = 'filteringBy' . ucfirst($data['field']);
        if (method_exists($handler, $methodName)) {
            $result['reportSpecific'] = $data;
            $result['callbacks'][]    = [$handler, $methodName];
        }
    }

    public function applyOnlyBookmarked(QueryBuilder $qb, IEntity $entity, array $params, Mapper $mapper): void
    {
        $ta = $mapper->getQueryConverter()->getMainTableAlias();

        $qb->andWhere("$ta.id IN (SELECT entity_id FROM bookmark WHERE entity_type = :bentityType AND user_id = :bUserId AND deleted = :false)")
            ->setParameter('bentityType', $entity->getEntityName())
            ->setParameter('bUserId', $this->getUser()->id)
            ->setParameter('false', false, ParameterType::BOOLEAN);
    }

    public function applyMultipleClassifications(QueryBuilder $qb, IEntity $entity, array $params, Mapper $mapper): void
    {
        $cqb       = $this->getEntityManager()->getRepository('Classification')->getMultipleClassificationsQb($entity->getEntityName());
        $mainAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $qb->andWhere($qb->expr()->in("$mainAlias.id", $cqb->getSQL()));
    }

    protected function hasCreatedByField(): bool
    {
        return $this->getSeed()->hasAttribute('createdById');
    }

    protected function hasTeamsField(): bool
    {
        return $this->getSeed()->hasRelation('teams') && $this->getSeed()->hasAttribute('teamsIds');
    }

    public function dispatch(string $scope, string $action, Event $event): Event
    {
        return $this->getMetadata()->getEventManager()->dispatch($scope, $action, $event);
    }

    protected function prepareSortBy(array &$params, bool $withAcl = false): void
    {
        if (empty($params['sortBy']) || !$withAcl) {
            return;
        }

        $sortBy = $params['sortBy'];
        $forbiddenAttributeList = $this->getAcl()->getScopeForbiddenAttributeList($this->getEntityType());

        $isExists = false;
        if ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $sortBy])) {
            $isExists = true;
        } elseif (!empty($this->getMetadata()->get(['scopes', $this->getEntityType(), 'hasAttribute']))) {
            if (!isset($this->cachedData['sortByAttributes'][$this->getEntityType()][$sortBy])) {
                // attribute fields are sorted by "{name}", "{name}Unit", "{name}From" or "{name}To"
                $ids = array_values(array_unique([$sortBy, preg_replace('/(Unit|From|To)$/', '', $sortBy)]));

                $this->cachedData['sortByAttributes'][$this->getEntityType()][$sortBy] = $this
                    ->getEntityManager()
                    ->getRepository('Attribute')
                    ->select(['id'])
                    ->where(['OR' => ['systemName' => $ids, 'id' => $ids], 'entityId' => $this->getEntityType()])
                    ->findOne() !== null;
            }
            $isExists = $this->cachedData['sortByAttributes'][$this->getEntityType()][$sortBy];
        }

        if ($isExists && !in_array($sortBy, $forbiddenAttributeList)) {
            return;
        }

        $defaultSortBy = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'collection', 'sortBy'], 'id');
        $params['sortBy'] = !in_array($defaultSortBy, $forbiddenAttributeList) ? $defaultSortBy : 'id';
    }

    public function getSelectParams(array $params, bool $withAcl = false, bool $checkWherePermission = false): array
    {
        $params = $this
            ->dispatch('Entity', 'beforeGetSelectParams', new Event(['params' => $params, 'entityType' => $this->entityType]))
            ->getArgument('params');

        $this->prepareActionExecutionFilter($params);
        $this->prepareSortBy($params, $withAcl);

        $this->selectParameters = $params;

        $result = [];
        $this->prepareResult($result);

        if (empty($params['sortBy'])) {
            $params['sortBy'] = 'id';
        }
        $params['asc'] ??= true;
        $params['offset'] ??= null;
        $params['maxSize'] ??= null;

        $this->order($params['sortBy'], !$params['asc'], $result);
        $this->limit($params['offset'], $params['maxSize'], $result);

        if (!empty($params['primaryFilter'])) {
            $this->applyPrimaryFilter($params['primaryFilter'], $result);
        }

        if (!empty($params['boolFilterList']) && is_array($params['boolFilterList'])) {
            foreach ($params['boolFilterList'] as $filterName) {
                $this->applyBoolFilter($filterName, $result);
            }
        }

        if (!empty($params['filterList']) && is_array($params['filterList'])) {
            foreach ($params['filterList'] as $filterName) {
                $this->applyPrimaryFilter($filterName, $result);
            }
        }

        if (!empty($params['where']) && is_array($params['where'])) {
            $params['where'] = $this->sanitizeWhereCondition($params['where']);
            $this->mutateWhereQuery($params['where'], $result);
            if ($checkWherePermission) {
                $this->checkWhere($params['where']);
            }
            $this->where($params['where'], $result);
        }

        if (isset($params['textFilter']) && $params['textFilter'] !== '') {
            $this->textFilter($params['textFilter'], $result);
        }

        $this->q($params, $result);

        // an entity with the archive shows the archived records only when a filter asks for them
        if (
            $this->getMetadata()->get(['scopes', $this->entityType, 'hasArchive'])
            && !isset($result['withArchived'])
            && !$this->hasArchivedCondition($result['whereClause'])
        ) {
            $result['whereClause'][] = ['isArchived' => false];
        }

        if ($withAcl) {
            $this->access($result);
        }

        $this->applyAdditional($result, $params);

        if (!empty($params['withDeleted'])) {
            $result['withDeleted'] = true;
        }

        if (isset($params['queryCallbacks'])) {
            $result['callbacks'] = $params['queryCallbacks'];
        }

        if (isset($params['subQueryCallbacks'])) {
            $result['subQueryCallbacks'] = $params['subQueryCallbacks'];
        }

        return $this
            ->dispatch('Entity', 'afterGetSelectParams', new Event(['result' => $result, 'params' => $params, 'entityType' => $this->entityType]))
            ->getArgument('result');
    }

    private function hasArchivedCondition(array $whereClause): bool
    {
        foreach ($whereClause as $row) {
            if (isset($row['isArchived=']) || isset($row['isArchived'])) {
                return true;
            }
        }

        return false;
    }

    public function applyActionExecutionFilter(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        if (empty($this->actionExecutionFilter)) {
            return;
        }

        if ($this->actionExecutionFilter['ruleId'] === 'createdCountFilterActionExecution') {
            $type = 'create';
        } elseif ($this->actionExecutionFilter['ruleId'] === 'updatedCountFilterActionExecution') {
            $type = 'update';
        } else {
            return;
        }

        if ($this->actionExecutionFilter['operator'] === 'in') {
            $operator = 'IN';
        } elseif ($this->actionExecutionFilter['operator'] === 'not_in') {
            $operator = 'NOT IN';
        } else {
            return;
        }

        $alias = $mapper->getQueryConverter()->getMainTableAlias();

        if ($this->getEntityManager()->getRepository('ActionExecutionLog')->hasClickHouse()) {
            $collection = $this->getEntityManager()->getRepository('ActionExecutionLog')
                ->where([
                    'actionExecutionId' => $this->actionExecutionFilter['actionExecutionId'],
                    'type'              => $type
                ])
                ->find();

            $qb
                ->andWhere("$alias.id $operator (:entityIds)")
                ->setParameter("entityIds", array_column($collection->toArray(), 'entityId'), Connection::PARAM_STR_ARRAY);
        } else {
            $qb->andWhere(
                "$alias.id $operator (SELECT ael.entity_id FROM action_execution_log ael WHERE ael.deleted=:false AND ael.type=:actionType AND ael.action_execution_id=:actionExecutionId)"
            );

            $qb->setParameter('false', false, ParameterType::BOOLEAN);
            $qb->setParameter('actionType', $type);
            $qb->setParameter('actionExecutionId', $this->actionExecutionFilter['actionExecutionId']);
        }
    }

    protected function prepareActionExecutionFilter(array &$params): void
    {
        foreach ($params['where'] ?? [] as $key => $row) {
            foreach ($row['rules'] ?? [] as $rule) {
                if (empty($rule['id'])) {
                    continue;
                }

                if (in_array($rule['id'], ['createdCountFilterActionExecution', 'updatedCountFilterActionExecution'])) {
                    $this->actionExecutionFilter = [
                        'ruleId'            => $rule['id'],
                        'operator'          => $rule['operator'],
                        'actionExecutionId' => $rule['value'][0] ?? null
                    ];

                    unset($params['where'][$key]);
                    $params['where'] = array_values($params['where']);

                    $params['filterCallbacks'][] = [$this, 'applyActionExecutionFilter'];
                }
            }
        }
    }

    protected function sanitizeWhereCondition(array $where): array
    {
        $clean = [];
        foreach ($where as $key => $item) {
            if (!is_array($item)) {
                $clean[$key] = $item;
                continue;
            }
            if (isset($item['type']) && is_string($item['type']) && strtolower($item['type']) === 'innersql') {
                continue;
            }
            unset($item['innerSql']);
            if (isset($item['rules']) && is_array($item['rules'])) {
                $item['rules'] = $this->sanitizeWhereCondition($item['rules']);
            }
            if (isset($item['value']) && is_array($item['value'])) {
                unset($item['value']['innerSql']);
                $item['value'] = $this->sanitizeWhereCondition($item['value']);
            }
            $clean[$key] = $item;
        }

        return $clean;
    }

    protected function checkWhere(array $where): void
    {
        foreach ($where as $w) {
            $attribute = $w['attribute'] ?? $w['field'] ?? null;
            if ($attribute) {
                $forbiddenList = in_array($w['type'] ?? null, ['isLinked', 'isNotLinked', 'linkedWith', 'notLinkedWith'])
                    ? $this->getAcl()->getScopeForbiddenFieldList($this->getEntityType())
                    : $this->getAcl()->getScopeForbiddenAttributeList($this->getEntityType());

                if (in_array($attribute, $forbiddenList)) {
                    throw new Forbidden();
                }
            }

            if (!empty($w['value']) && is_array($w['value'])) {
                $this->checkWhere($w['value']);
            }
        }
    }

    public function convertDateTimeWhere(array $item): array
    {
        $where = $this->getColumnWhereConverter()->prepareDateTimeItem($item);

        return $where === null ? [] : $this->getWherePart($where);
    }

    protected function getColumnWhereConverter(): ColumnWhereConverter
    {
        return $this->container->get(ColumnWhereConverter::class);
    }

    protected function convertUnitFieldWhere(array $item): array
    {
        $type            = $item['type'];
        $value           = $item['value'] ?? null;
        $mainField       = $item['attribute'];
        $mainFieldColumn = Util::toUnderScore($mainField);
        $unitColumn      = $mainFieldColumn . '_unit_id';
        $ta              = QueryConverter::TABLE_ALIAS;
        $isInt           = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $mainField, 'type']) === 'int';

        if ($type === 'isNull') {
            return [
                'AND' => [
                    [$mainField . '=' => null],
                    [$mainField . 'UnitId=' => null],
                ],
            ];
        }

        if ($type === 'isNotNull') {
            return [
                'AND' => [
                    [$mainField . '!=' => null],
                    [$mainField . 'UnitId!=' => null],
                ],
            ];
        }

        // Pre-convert filter value into each unit used in the table for sargable (index-friendly) queries
        $tableName    = Util::toUnderScore($this->entityType);
        $measureUnits = $this->getMeasureUnitsData($tableName, $unitColumn);
        if (empty($measureUnits)) {
            return [];
        }

        if ($type === 'between') {
            if (!is_array($value) || count($value) < 2) {
                return [];
            }

            $baseFrom = $this->resolveUnitFilterValue($value[0], $measureUnits);
            $baseTo   = $this->resolveUnitFilterValue($value[1], $measureUnits);
            if ($baseFrom === null || $baseTo === null) {
                return [];
            }

            $orParts    = [];
            $parameters = [];
            foreach ($measureUnits as $uid => $multiplier) {
                $pUid               = 'uf_uid_' . IdGenerator::unsortableId();
                $pFrom              = 'uf_from_' . IdGenerator::unsortableId();
                $pTo                = 'uf_to_' . IdGenerator::unsortableId();
                $orParts[]          = "($ta.$unitColumn = :$pUid AND $ta.$mainFieldColumn >= :$pFrom AND $ta.$mainFieldColumn <= :$pTo)";
                $parameters[$pUid]  = $uid;
                $parameters[$pFrom] = $isInt ? (int)round($baseFrom / $multiplier) : $baseFrom / $multiplier;
                $parameters[$pTo]   = $isInt ? (int)round($baseTo / $multiplier) : $baseTo / $multiplier;
            }

            return [
                'innerSql' => [
                    'sql'        => '(' . implode(' OR ', $orParts) . ')',
                    'parameters' => $parameters,
                ],
            ];
        }

        $sqlOperator = $this->getUnitFilterSqlOperator($type);
        if ($sqlOperator === null) {
            return [];
        }

        $baseValue = $this->resolveUnitFilterValue($value, $measureUnits);
        if ($baseValue === null) {
            return [];
        }

        $orParts    = [];
        $parameters = [];
        foreach ($measureUnits as $uid => $multiplier) {
            $pUid              = 'uf_uid_' . IdGenerator::unsortableId();
            $pVal              = 'uf_val_' . IdGenerator::unsortableId();
            $orParts[]         = "($ta.$unitColumn = :$pUid AND $ta.$mainFieldColumn $sqlOperator :$pVal)";
            $parameters[$pUid] = $uid;
            $parameters[$pVal] = $isInt ? (int)round($baseValue / $multiplier) : $baseValue / $multiplier;
        }

        return [
            'innerSql' => [
                'sql'        => '(' . implode(' OR ', $orParts) . ')',
                'parameters' => $parameters,
            ],
        ];
    }

    protected function getMeasureUnitsData(string $tableName, string $unitColumn): array
    {
        $cacheKey = $tableName . '.' . $unitColumn;

        if (!isset($this->cachedData['measureUnits'][$cacheKey])) {
            $rows = $this->getEntityManager()->getDbal()->createQueryBuilder()
                ->select('u.id', 'u.multiplier')
                ->from('unit', 'u')
                ->innerJoin('u', "(SELECT DISTINCT $unitColumn AS uid FROM $tableName WHERE $unitColumn IS NOT NULL)", 'used', 'u.id = used.uid')
                ->where('u.deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            $units = [];
            foreach ($rows as $row) {
                $multiplier = (float)$row['multiplier'];
                if ($multiplier != 0) {
                    $units[$row['id']] = $multiplier;
                }
            }
            $this->cachedData['measureUnits'][$cacheKey] = $units;
        }

        return $this->cachedData['measureUnits'][$cacheKey];
    }

    protected function resolveUnitFilterValue($value, array $measureUnits): ?float
    {
        if (!is_array($value) || count($value) < 2) {
            return null;
        }

        $amount = $value[0];
        $unitId = $value[1];

        if (!is_numeric($amount) || empty($unitId)) {
            return null;
        }

        // The filter unit might not be used in the table — look it up if needed
        if (!isset($measureUnits[$unitId])) {
            if (!isset($this->cachedData['unitMultiplier'][$unitId])) {
                $row = $this->getEntityManager()->getDbal()->createQueryBuilder()
                    ->select('multiplier')
                    ->from('unit')
                    ->where('id = :id AND deleted = :false')
                    ->setParameter('id', $unitId)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchAssociative();

                $this->cachedData['unitMultiplier'][$unitId] = !empty($row) ? (float)$row['multiplier'] : null;
            }
            $multiplier = $this->cachedData['unitMultiplier'][$unitId];
        } else {
            $multiplier = $measureUnits[$unitId];
        }

        if (empty($multiplier)) {
            return null;
        }

        return (float)$amount * $multiplier;
    }

    protected function getUnitFilterSqlOperator(string $type): ?string
    {
        $map = [
            'equals'              => '=',
            'notEquals'           => '<>',
            'greaterThan'         => '>',
            'greaterThanOrEquals' => '>=',
            'lessThan'            => '<',
            'lessThanOrEquals'    => '<=',
        ];

        return $map[$type] ?? null;
    }

    protected function modifyPartForHierarchy(array &$item): void
    {
        $this->modifyRoutesField($item);
        $this->modifyParentField($item);
    }

    protected function modifyRoutesField(array &$item): void
    {
        if (isset($item['attribute']) && $item['attribute'] === 'routesId') {
            switch ($item['type']) {
                case 'in':
                    $values = [];
                    foreach ($item['value'] as $value) {
                        $values[] = [
                            'type'      => 'contains',
                            'attribute' => 'routes',
                            'value'     => $value
                        ];
                    }

                    $item = [
                        'type'  => 'or',
                        'value' => $values
                    ];
                    break;
                case 'notIn':
                    $values = [];
                    foreach ($item['value'] as $value) {
                        $values[] = [
                            'type'      => 'notContains',
                            'attribute' => 'routes',
                            'value'     => $value
                        ];
                    }

                    $item = [
                        'type'  => 'and',
                        'value' => $values
                    ];
                    break;
                case 'isNull':
                    $item = [
                        'type'      => 'arrayIsEmpty',
                        'attribute' => 'routes'
                    ];
                    break;
                case 'isNotNull':
                    $item = [
                        'type'      => 'arrayIsNotEmpty',
                        'attribute' => 'routes'
                    ];
                    break;
            }
        }
    }

    protected function modifyParentField(array &$item): void
    {
        if (isset($item['attribute']) && $item['attribute'] === 'parentId') {
            $subQuery = $item['subQuery'] ?? null;
            switch ($item['type']) {
                case 'equals':
                    $item = [
                        'type'      => 'linkedWith',
                        'attribute' => 'parents',
                        'value'     => [$item['value']],
                    ];
                    break;
                case 'in':
                    $item = [
                        'type'      => 'linkedWith',
                        'attribute' => 'parents',
                        'value'     => $item['value']
                    ];
                    break;
                case 'notEquals':
                    $item = [
                        'type'      => 'notLinkedWith',
                        'attribute' => 'parents',
                        'value'     => [$item['value']]
                    ];
                    break;
                case 'notIn':
                    $item = [
                        'type'      => 'notLinkedWith',
                        'attribute' => 'parents',
                        'value'     => $item['value']
                    ];
                    break;
                case 'isNull':
                    $item = [
                        'type'      => 'isNotLinked',
                        'attribute' => 'parents'
                    ];
                    break;
                case 'isNotNull':
                    $item = [
                        'type'      => 'isLinked',
                        'attribute' => 'parents'
                    ];
                    break;
            }
            if (!empty($subQuery)) {
                $item['subQuery'] = $subQuery;
            }
        }
    }

    protected function getLinkEntityForAttribute(string $attribute): ?string
    {
        if (!str_ends_with($attribute, 'Id')) {
            return null;
        }

        $entity = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', substr($attribute, 0, -2), 'entity']);

        return !empty($entity) ? $entity : null;
    }

    protected function buildForeignDeletedNullCheck(string $foreignEntity, string $attribute, bool $isNotNull): array
    {
        $mainAlias    = QueryConverter::TABLE_ALIAS;
        $column       = Util::toUnderScore($attribute);
        $foreignTable = $this->getEntityManager()->getDbal()->quoteIdentifier(Util::toUnderScore($foreignEntity));
        $uid          = IdGenerator::unsortableId();
        $alias        = 'lnkdel_' . $uid;
        $deletedParam = 'lnkdel_p_' . $uid;

        $existsDeleted = "EXISTS (SELECT 1 FROM $foreignTable $alias WHERE $alias.id = $mainAlias.$column AND $alias.deleted = :$deletedParam)";

        $sql = $isNotNull
            ? "($mainAlias.$column IS NOT NULL AND NOT $existsDeleted)"
            : "($mainAlias.$column IS NULL OR $existsDeleted)";

        return [
            'sql'        => $sql,
            'parameters' => [$deletedParam => true]
        ];
    }

    protected function getWherePart(array $item, ?array &$result = null): array
    {
        $part = [];

        $entityType = $this->getMetadata()->get(['scopes', $this->entityType, 'type']);

        $method = "modifyPartFor{$entityType}";
        if (method_exists($this, $method)) {
            $this->$method($item);
        }

        // "field" is for backward compatibility
        $attribute = !empty($item['attribute']) ? $item['attribute'] : (!empty($item['field']) ? $item['field'] : null);

        if (!is_null($attribute) && !is_string($attribute)) {
            throw new Error('Bad attribute in where statement');
        }

        // for backward compatibility (extensibleEnum)
        if (
            $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $attribute, 'type']) === 'link'
            && !in_array($item['type'] ?? '', ['linkedWith', 'notLinkedWith', 'isLinked', 'isNotLinked'])
        ) {
            $attribute .= 'Id';
        }

        // one-to-one relations have no key on this side, so such filters have to be converted to link filters
        if (!empty($attribute) && !empty($item['type'])) {
            $link = $attribute;
            if (
                $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $link, 'type']) !== 'hasOne'
                && str_ends_with($link, 'Id')
            ) {
                $link = substr($link, 0, -2);
            }

            if ($this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $link, 'type']) === 'hasOne') {
                $types = [
                    'in'            => 'linkedWith',
                    'equals'        => 'linkedWith',
                    'linkedWith'    => 'linkedWith',
                    'notIn'         => 'notLinkedWith',
                    'notEquals'     => 'notLinkedWith',
                    'notLinkedWith' => 'notLinkedWith',
                    'isNull'        => 'isNotLinked',
                    'isNotLinked'   => 'isNotLinked',
                    'isNotNull'     => 'isLinked',
                    'isLinked'      => 'isLinked',
                ];

                if (isset($types[$item['type']])) {
                    $attribute    = $link;
                    $item['type'] = $types[$item['type']];
                    if (isset($item['value']) && !is_array($item['value'])) {
                        $item['value'] = [$item['value']];
                    }
                }
            }
        }

        // for backward compatibility (extensibleMultiEnum)
        if ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $attribute, 'type']) === 'linkMultiple' && !empty($item['type'])) {
            $item['type'] = match ($item['type']) {
                'arrayAnyOf'      => 'linkedWith',
                'arrayNoneOf'     => 'notLinkedWith',
                'arrayIsEmpty'    => 'isNotLinked',
                'arrayIsNotEmpty' => 'isLinked',
                default           => $item['type'],
            };
        }

        if (
            !isset($item['dateTime'])
            && !empty($attribute)
            && $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $attribute, 'type']) === 'datetime'
        ) {
            $item['dateTime'] = true;
        }

        if (!empty($item['combinedField']) && empty($item['isAttribute']) && !empty($attribute) && !empty($item['type'])) {
            return $this->convertUnitFieldWhere($item);
        }

        if (!empty($item['isAttribute']) && $this->getMetadata()->get(['scopes', $this->entityType, 'hasAttribute'])) {
            unset($item['isAttribute']);
            $this->getEntityManager()->getAttributeFieldConverter()->getWherePart($this->getSeed(), $item, $result);
        }

        if (!empty($item['subQuery'])) {
            $link          = substr($attribute, -2) === 'Id' ? substr($attribute, 0, -2) : $attribute;
            $foreignEntity = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $link, 'entity']) ?? $item['foreignEntity'] ?? null;
            $foreignField  = $item['foreignField'] ?? 'id';
            if (!empty($foreignEntity)) {
                $foreignRepository             = $this->getEntityManager()->getRepository($foreignEntity);
                $sp                            = $this->createSelectManager($foreignEntity)->getSelectParams(['where' => $item['subQuery']], true, true);
                $sp['select']                  = [$foreignField];
                $sp['additionalSelectColumns'] = [];
                $sp['orderBy']                 = null;
                $qb1                           = $foreignRepository->getMapper()->createSelectQueryBuilder($foreignRepository->get(), $sp, true);
                $item['value']                 = [
                    "innerSql" => [
                        "sql"        => str_replace($this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias(), 'sbq_' . IdGenerator::unsortableId(), $qb1->getSql()),
                        "parameters" => $qb1->getParameters()
                    ]
                ];
            }
            unset($item['subQuery']);
        }

        if (!empty($attribute) && !empty($item['type'])) {
            $methodName = 'getWherePartFor' . ucfirst($attribute);
            if (method_exists($this, $methodName)) {
                return $this->$methodName($item, $result);
            }
        }

        if (!empty($item['dateTime'])) {
            return $this->convertDateTimeWhere($item);
        }

        $value = $item['value'] ?? null;

        if (!empty($item['type'])) {
            $type = $item['type'];

            switch ($type) {
                case 'innerSql':
                    $part['innerSql'] = $value;
                    break;
                case 'or':
                case 'and':
                case 'not':
                    if (is_array($value)) {
                        $arr = [];
                        foreach ($value as $i) {
                            $a = $this->getWherePart($i, $result);
                            foreach ($a as $left => $right) {
                                if (!empty($right) || is_null($right) || $right === '' || $right === 0 || $right === '0' || $right === false) {
                                    $arr[] = [$left => $right];
                                }
                            }
                        }
                        $part[strtoupper($type)] = $arr;
                    }
                    break;

                case 'isNull':
                case 'isNotNull':
                    $foreignEntity = $this->getLinkEntityForAttribute($attribute);
                    if ($foreignEntity !== null) {
                        $part['innerSql'] = $this->buildForeignDeletedNullCheck($foreignEntity, $attribute, $type === 'isNotNull');
                    } else {
                        $part = $this->getColumnWhereConverter()->convert($attribute, $type, $value);
                    }
                    break;

                case 'isNotLinked':
                    if (!$result) {
                        break;
                    }

                    $link = $attribute;
                    /** @var Entity $seed */
                    $seed         = $this->getSeed();
                    $relationType = $seed->getRelationType($link);

                    if ($relationType === 'manyMany') {
                        $midKeys      = $seed->getRelationParam($attribute, 'midKeys');
                        $relationName = $seed->getRelationParam($attribute, 'relationName');
                        $relTable     = Util::toUnderScore($relationName);
                        $nearKey      = Util::toUnderScore($midKeys[0]);
                        $distantKey   = Util::toUnderScore($midKeys[1]);
                        $foreignTable = Util::toUnderScore($seed->getRelationParam($attribute, 'entity'));
                        $uid          = IdGenerator::unsortableId();
                        $exAlias      = 'inl_' . $uid;
                        $foreignAlias = 'inlf_' . $uid;
                        $falseParam   = 'inl_del_' . $uid;
                        $ta           = $this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias();

                        $part['innerSql'] = [
                            'sql'        => "NOT EXISTS (SELECT 1 FROM {$relTable} {$exAlias} JOIN {$foreignTable} {$foreignAlias} ON {$foreignAlias}.id = {$exAlias}.{$distantKey} AND {$foreignAlias}.deleted = :{$falseParam} WHERE {$exAlias}.{$nearKey} = {$ta}.id AND {$exAlias}.deleted = :{$falseParam})",
                            'parameters' => [$falseParam => false],
                        ];
                    } elseif (in_array($relationType, ['hasMany', 'hasOne'])) {
                        $foreignKey   = Util::toUnderScore($seed->getRelationParam($link, 'foreignKey') ?? (lcfirst($seed->getEntityType()) . 'Id'));
                        $foreignTable = Util::toUnderScore($seed->getRelationParam($link, 'entity'));
                        $uid          = IdGenerator::unsortableId();
                        $exAlias      = 'inl_' . $uid;
                        $falseParam   = 'inl_del_' . $uid;
                        $ta           = $this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias();

                        $part['innerSql'] = [
                            'sql'        => "NOT EXISTS (SELECT 1 FROM {$foreignTable} {$exAlias} WHERE {$exAlias}.{$foreignKey} = {$ta}.id AND {$exAlias}.deleted = :{$falseParam})",
                            'parameters' => [$falseParam => false],
                        ];
                    } elseif (in_array($relationType, ['belongsTo', 'belongsToParent'])) {
                        $key        = $seed->getRelationParam($link, 'key') ?? ($link . 'Id');
                        $part[$key] = null;
                    }

                    break;

                case 'isLinked':
                    if (!$result) {
                        break;
                    }

                    $seed         = $this->getSeed();
                    $relationType = $seed->getRelationType($attribute);
                    $uid          = IdGenerator::unsortableId();
                    $falseParam   = 'il_del_' . $uid;

                    $ta = $this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias();

                    if ($relationType === 'manyMany') {
                        $midKeys      = $seed->getRelationParam($attribute, 'midKeys');
                        $relationName = $seed->getRelationParam($attribute, 'relationName');
                        $relTable     = Util::toUnderScore($relationName);
                        $nearKey      = Util::toUnderScore($midKeys[0]);
                        $distantKey   = Util::toUnderScore($midKeys[1]);
                        $foreignTable = Util::toUnderScore($seed->getRelationParam($attribute, 'entity'));
                        $exAlias      = 'il_' . $uid;
                        $foreignAlias = 'ilf_' . $uid;

                        $part['innerSql'] = [
                            'sql'        => "EXISTS (SELECT 1 FROM {$relTable} {$exAlias} JOIN {$foreignTable} {$foreignAlias} ON {$foreignAlias}.id = {$exAlias}.{$distantKey} AND {$foreignAlias}.deleted = :{$falseParam} WHERE {$exAlias}.{$nearKey} = {$ta}.id AND {$exAlias}.deleted = :{$falseParam})",
                            'parameters' => [$falseParam => false],
                        ];
                    } elseif (in_array($relationType, ['hasMany', 'hasOne'])) {
                        $foreignKey   = $seed->getRelationParam($attribute, 'foreignKey') ?? (lcfirst($seed->getEntityType()) . 'Id');
                        $foreignKey   = Util::toUnderScore($foreignKey);
                        $foreignTable = Util::toUnderScore($seed->getRelationParam($attribute, 'entity'));
                        $exAlias      = 'il_' . $uid;

                        $part['innerSql'] = [
                            'sql'        => "EXISTS (SELECT 1 FROM {$foreignTable} {$exAlias} WHERE {$exAlias}.{$foreignKey} = {$ta}.id AND {$exAlias}.deleted = :{$falseParam})",
                            'parameters' => [$falseParam => false],
                        ];
                    } elseif (in_array($relationType, ['belongsTo', 'belongsToParent'])) {
                        $key               = $seed->getRelationParam($attribute, 'key') ?? ($attribute . 'Id');
                        $part[$key . '!='] = null;
                    }
                    break;

                case 'linkedWith':
                    $seed = $this->getSeed();
                    $link = $attribute;
                    if (!$seed->hasRelation($link)) {
                        break;
                    }

                    if (is_null($value) || !$value && !is_array($value)) {
                        break;
                    }

                    $relationType = $seed->getRelationType($link);

                    if ($relationType == 'manyMany') {
                        $midKeys      = $seed->getRelationParam($link, 'midKeys');
                        $relationName = $seed->getRelationParam($link, 'relationName');
                        $relTable     = Util::toUnderScore($relationName);
                        $nearKey      = Util::toUnderScore($midKeys[0]);
                        $distantKey   = Util::toUnderScore($midKeys[1]);
                        $uid          = IdGenerator::unsortableId();
                        $falseParam   = 'mm_del_' . $uid;

                        if (is_array($value) && isset($value['innerSql'])) {
                            $part['innerSql'] = [
                                'sql'        => "SELECT {$nearKey} FROM {$relTable} WHERE deleted = :{$falseParam} AND {$distantKey} IN ({$value['innerSql']['sql']})",
                                'parameters' => array_merge([$falseParam => false], $value['innerSql']['parameters']),
                            ];
                        } else {
                            $idsParam         = 'mm_ids_' . $uid;
                            $part['innerSql'] = [
                                'sql'        => "SELECT {$nearKey} FROM {$relTable} WHERE deleted = :{$falseParam} AND {$distantKey} IN (:{$idsParam})",
                                'parameters' => [$falseParam => false, $idsParam => $value],
                            ];
                        }

                        $part = ['id' => $part];
                    } elseif (in_array($relationType, ['hasMany', 'hasOne'])) {
                        $foreignKey   = Util::toUnderScore($seed->getRelationParam($link, 'foreignKey') ?? (lcfirst($seed->getEntityType()) . 'Id'));
                        $foreignTable = Util::toUnderScore($seed->getRelationParam($link, 'entity'));
                        $uid          = IdGenerator::unsortableId();
                        $falseParam   = 'lw_del_' . $uid;

                        if (is_array($value) && isset($value['innerSql'])) {
                            $part = [
                                'id' => [
                                    'innerSql' => [
                                        'sql'        => "SELECT {$foreignKey} FROM {$foreignTable} WHERE deleted = :{$falseParam} AND id IN ({$value['innerSql']['sql']})",
                                        'parameters' => array_merge([$falseParam => false], $value['innerSql']['parameters']),
                                    ],
                                ],
                            ];
                        } else {
                            $idsParam = 'lw_ids_' . $uid;
                            $part     = [
                                'id' => [
                                    'innerSql' => [
                                        'sql'        => "SELECT {$foreignKey} FROM {$foreignTable} WHERE deleted = :{$falseParam} AND id IN (:{$idsParam})",
                                        'parameters' => [$falseParam => false, $idsParam => $value],
                                    ],
                                ],
                            ];
                        }
                    } elseif (in_array($relationType, ['belongsTo', 'belongsToParent'])) {
                        $key = $seed->getRelationParam($link, 'key');
                        if (!empty($key)) {
                            $part[$key] = $value;
                        }
                    }
                    break;

                case 'notLinkedWith':
                    $seed = $this->getSeed();
                    $link = $attribute;
                    if (!$seed->hasRelation($link)) {
                        break;
                    }

                    if (is_null($value)) {
                        break;
                    }

                    $relationType = $seed->getRelationType($link);

                    if ($relationType == 'manyMany') {
                        $midKeys      = $seed->getRelationParam($link, 'midKeys');
                        $relationName = $seed->getRelationParam($link, 'relationName');
                        $relTable     = Util::toUnderScore($relationName);
                        $nearKey      = Util::toUnderScore($midKeys[0]);
                        $distantKey   = Util::toUnderScore($midKeys[1]);
                        $uid          = IdGenerator::unsortableId();
                        $falseParam   = 'mm_ndel_' . $uid;

                        if (is_array($value) && isset($value['innerSql'])) {
                            $part = [
                                'id!=' => [
                                    'innerSql' => [
                                        'sql'        => "SELECT {$nearKey} FROM {$relTable} WHERE deleted = :{$falseParam} AND {$distantKey} IN ({$value['innerSql']['sql']})",
                                        'parameters' => array_merge([$falseParam => false], $value['innerSql']['parameters']),
                                    ],
                                ],
                            ];
                        } else {
                            $idsParam = 'mm_nids_' . $uid;
                            $part     = [
                                'id!=' => [
                                    'innerSql' => [
                                        'sql'        => "SELECT {$nearKey} FROM {$relTable} WHERE deleted = :{$falseParam} AND {$distantKey} IN (:{$idsParam})",
                                        'parameters' => [$falseParam => false, $idsParam => $value],
                                    ],
                                ],
                            ];
                        }
                    } elseif (in_array($relationType, ['hasMany', 'hasOne'])) {
                        $foreignKey   = Util::toUnderScore($seed->getRelationParam($link, 'foreignKey') ?? (lcfirst($seed->getEntityType()) . 'Id'));
                        $foreignTable = Util::toUnderScore($seed->getRelationParam($link, 'entity'));
                        $uid          = IdGenerator::unsortableId();
                        $falseParam   = 'nlw_del_' . $uid;

                        if (is_array($value) && isset($value['innerSql'])) {
                            $part = [
                                'id!=' => [
                                    'innerSql' => [
                                        'sql'        => "SELECT {$foreignKey} FROM {$foreignTable} WHERE deleted = :{$falseParam} AND {$foreignKey} IS NOT NULL AND id IN ({$value['innerSql']['sql']})",
                                        'parameters' => array_merge([$falseParam => false], $value['innerSql']['parameters']),
                                    ],
                                ],
                            ];
                        } else {
                            $idsParam = 'nlw_ids_' . $uid;
                            $part     = [
                                'id!=' => [
                                    'innerSql' => [
                                        'sql'        => "SELECT {$foreignKey} FROM {$foreignTable} WHERE deleted = :{$falseParam} AND {$foreignKey} IS NOT NULL AND id IN (:{$idsParam})",
                                        'parameters' => [$falseParam => false, $idsParam => $value],
                                    ],
                                ],
                            ];
                        }
                    } elseif (in_array($relationType, ['belongsTo', 'belongsToParent'])) {
                        $key = $seed->getRelationParam($link, 'key');
                        if (!empty($key)) {
                            $part[$key . '!='] = $value;
                        }
                    }
                    break;
                case 'savedSearch':
                    if (empty($value) || !is_array($value)) {
                        break;
                    }

                    $entities = $this->getEntityManager()->getRepository('SavedSearch')->where(['id' => $value])->find();

                    if (empty($entities)) {
                        break;
                    }

                    $item = [
                        "type"  => "and",
                        "value" => []
                    ];

                    foreach ($entities as $entity) {
                        if (empty($entity->get('data'))) {
                            continue;
                        }
                        $where = [json_decode(json_encode($entity->get('data')), true)];
                        $this->mutateWhereQuery($where, $result);
                        $item["value"][] = $where[0];
                    }

                    if (!empty($item["value"])) {
                        $part = $this->getWherePart($item, $result);
                    }

                default:
                    if (in_array($type, ['similar', 'wordSimilar'], true) && !$this->isKnownField((string)$attribute)) {
                        throw new BadRequest(sprintf($this->getLanguage()->translate('filterContainsUnknownField', 'exceptions', 'Global'), $attribute));
                    }
                    if ($this->getColumnWhereConverter()->isSupported($type)) {
                        $part = $this->getColumnWhereConverter()->convert((string)$attribute, $type, $value);
                    }
            }
        }

        return $part;
    }

    /**
     * Applies the primary filter {name} by the method filter{Name} of the select manager.
     */
    public function applyPrimaryFilter(string $filterName, array &$result): void
    {
        $this->prepareResult($result);

        $method = 'filter' . ucfirst($filterName);
        if (method_exists($this, $method)) {
            $this->$method($result);
        }
    }

    /**
     * Applies the bool filter {name} by the method boolFilter{Name} of the select manager.
     */
    public function applyBoolFilter(string $filterName, array &$result): void
    {
        $this->prepareResult($result);

        $method = 'boolFilter' . ucfirst($filterName);
        if (method_exists($this, $method)) {
            $this->$method($result);
        }
    }

    public function applyTextFilter(string $textFilter, array &$result): void
    {
        $this->prepareResult($result);
        $this->textFilter($textFilter, $result);
    }

    /**
     * Lets a select manager of an entity add its conditions to the select parameters.
     */
    public function applyAdditional(array &$result, array $params): void
    {
    }

    public function addLeftJoin(string|array $leftJoin, array &$result): void
    {
        $result['leftJoins'] ??= [];

        $alias = $this->getJoinAlias($leftJoin);
        foreach ($result['leftJoins'] as $join) {
            if ($this->getJoinAlias($join) === $alias) {
                return;
            }
        }

        $result['leftJoins'][] = $leftJoin;
    }

    private function getJoinAlias(string|array $join): string
    {
        return is_array($join) ? ($join[1] ?? $join[0]) : $join;
    }

    public function setDistinct(bool $distinct, array &$result): void
    {
        $result['distinct'] = $distinct;
    }

    protected function isValidNumber(string $value, string $thousandSeparator = ',', string $decimalSeparator = '.'): bool
    {
        $escapedThousandSeparator = preg_quote($thousandSeparator, '/');
        $escapedDecimalSeparator  = preg_quote($decimalSeparator, '/');

        $pattern = '/^';
        $pattern .= '-?';
        $pattern .= '\d+';
        $pattern .= '(?:' . $escapedThousandSeparator . '\d{3})*';
        $pattern .= '(?:' . $escapedDecimalSeparator . '\d+)?';
        $pattern .= '$/';

        return (bool)preg_match($pattern, $value);
    }

    protected function textFilter(string $textFilter, array &$result): void
    {
        $autocompletion = false;
        if (str_starts_with($textFilter, 'AUTOCOMPLETE:')) {
            $textFilter     = mb_substr($textFilter, strlen('AUTOCOMPLETE:'));
            $autocompletion = true;
        }
        $type      = $this->getMetadata()->get(['scopes', $this->getEntityType(), 'type']);
        $fieldDefs = $this->getSeed()->getAttributes();
        $fieldList = $this->getTextFilterFieldList();
        $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($this->getEntityType(), 'read');
        if (!empty($forbiddenFieldList)) {
            $fieldList = array_values(array_filter($fieldList, function ($field) use ($forbiddenFieldList) {
                return $field === 'id' || !in_array($field, $forbiddenFieldList);
            }));
        }
        $group     = [];

        $textFilterContainsMinLength = $this->getConfig()->get('textFilterContainsMinLength', self::MIN_LENGTH_FOR_CONTENT_SEARCH);

        if (str_starts_with($textFilter, 'ft:')) {
            $textFilter = mb_substr($textFilter, 3);
        }

        $skipWildcards = false;

        if (str_contains($textFilter, '*')) {
            $skipWildcards = true;
            $textFilter    = str_replace('*', '%', $textFilter);
        }

        $fuzzyTextTypes = ['varchar', 'text', 'wysiwyg'];
        $useFuzzy       = $type !== 'ReferenceData' && !$skipWildcards
            && mb_strlen($textFilter) >= 3
            && class_exists('AdvancedDataManagement\Core\FuzzySearch') &&
            \AdvancedDataManagement\Core\FuzzySearch::isAvailable($this->getEntityManager()->getDbal());

        $fuzzyFields = [];

        foreach ($fieldList as $field) {
            $attributeType = null;
            if (!empty($fieldDefs[$field]['type'])) {
                $attributeType = $fieldDefs[$field]['type'];
            }

            if ($useFuzzy && in_array($attributeType, $fuzzyTextTypes)) {
                $fuzzyFields[] = $field;
                continue;
            }

            if ($useFuzzy && in_array($attributeType, ['int', 'float', 'bool', 'date', 'datetime', 'enum'])) {
                continue;
            }

            if (in_array($attributeType, ['int', 'float'])) {
                $thousandSeparator = $this->getConfig()->get('thousandSeparator', ',');
                $decimalMark       = $this->getConfig()->get('decimalMark', '.');
                if ($this->getUser() && !empty($locale = $this->getUser()->getLocale())) {
                    $thousandSeparator = $locale->get('thousandSeparator') ?? $thousandSeparator;
                    $decimalMark       = $locale->get('decimalMark') ?? $decimalMark;
                }

                if ($this->isValidNumber($textFilter, $thousandSeparator, $decimalMark)) {
                    $value = str_replace($thousandSeparator, '', $textFilter);
                    $value = str_replace($decimalMark, '.', $value);
                    $value = $attributeType === 'int' ? intval($value) : floatval($value);

                    $int32Max = (2 ** 31) - 1;

                    // avoid the range limit for INTEGER in database
                    if ($attributeType === 'int' && abs($value) > $int32Max) {
                        continue;
                    }
                    $group[$field] = $value;

                }
                continue;
            }

            if (Converter::isPgSQL($this->getEntityManager()->getDbal()) &&
                in_array($attributeType, ['bool', 'date', 'datetime'])) {
                $field = "VARCHAR:$field";
            }

            if (!$skipWildcards) {
                if (
                    mb_strlen($textFilter) >= $textFilterContainsMinLength
                    && (
                        $attributeType == 'text'
                        || in_array($field, $this->textFilterUseContainsAttributeList)
                        || $attributeType == 'varchar' && $this->getConfig()->get('textFilterUseContainsForVarchar')
                    )
                ) {
                    $expression = '%' . $textFilter . '%';
                } else {
                    $expression = $textFilter . '%';
                }
            } else {
                $expression = $textFilter;
            }

            $group[$field . '*'] = $expression;

            if ($field === 'name' && $autocompletion) {
                $result['callbacks'][] = function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($expression) {
                    $ta        = $mapper->getQueryConverter()->getMainTableAlias();
                    $parameter = 'name_' . IdGenerator::unsortableId();
                    $alias     = 'match_priority_' . IdGenerator::unsortableId();
                    $qb->addSelect("CASE WHEN LOWER($ta.name) LIKE LOWER(:$parameter) THEN 1 ELSE 2 END AS $alias");
                    $qb->orderBy("$alias");
                    $qb->addOrderBy("$ta.name");
                    $qb->setParameter($parameter, $expression);
                };
            }
        }

        if (!empty($fuzzyFields)) {
            $threshold = (float)$this->getConfig()->get('similarityThreshold', 1);
            $words     = array_values(array_filter(array_map('trim', explode(' ', $textFilter))));
            $ta        = QueryConverter::TABLE_ALIAS;

            $groupItem = [
                'AND' => []
            ];

            // Each word must match at least one fuzzy field (AND of OR-per-field), added via innerSql
            foreach ($words as $word) {
                $paramVal   = 'ftrgm_v_' . IdGenerator::unsortableId();
                $paramThr   = 'ftrgm_t_' . IdGenerator::unsortableId();
                $conditions = [];
                foreach ($fuzzyFields as $f) {
                    $colName      = Util::toUnderScore($f);
                    $conditions[] = "word_similarity(:$paramVal, $ta.$colName) >= :$paramThr";
                }
                $groupItem['AND'][] = [
                    'innerSql' => [
                        'sql'        => '(' . implode(' OR ', $conditions) . ')',
                        'parameters' => [$paramVal => $word, $paramThr => $threshold],
                    ],
                ];
            }

            $group[] = $groupItem;

            // Autocomplete: order by similarity of full input to name (requires callback for addSelect/orderBy)
            if ($autocompletion && in_array('name', $fuzzyFields)) {
                $paramVal              = 'ftrgm_ord_' . IdGenerator::unsortableId();
                $scoreAlias            = 'fuzzy_name_score_' . IdGenerator::unsortableId();
                $result['callbacks'][] = function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($paramVal, $scoreAlias, $textFilter) {
                    $ta      = $mapper->getQueryConverter()->getMainTableAlias();
                    $colName = Util::toUnderScore('name');
                    $qb->addSelect("word_similarity(:$paramVal, $ta.$colName) AS $scoreAlias");
                    $qb->orderBy($scoreAlias, 'DESC');
                    $qb->addOrderBy("$ta.$colName");
                    $qb->setParameter($paramVal, $textFilter);
                };
            }
        }

        $result['whereClause'][] = empty($group) ? ['id' => null] : ['OR' => $group];
    }

    public function applyAccess(array &$result): void
    {
        $this->prepareResult($result);
        $this->access($result);
    }

    protected function boolFilterOnlyMy(array &$result): void
    {
        $userId = $this->getUser()->id;

        $where = [];
        if ($this->hasOwnerUserField()) {
            $where[] = ['ownerUserId' => $userId];
        }
        if ($this->hasAssignedUserField()) {
            $where[] = ['assignedUserId' => $userId];
        }
        if (!$this->hasOwnerUserField() && !$this->hasAssignedUserField()) {
            $where[] = ['createdById' => $userId];
        }

        $result['whereClause'][] = ['OR' => $where];
    }

    protected function boolFilterOnlyMyTeams(array &$result): void
    {
        if ($this->hasTeamsField()) {
            $result['callbacks'][] = [$this, 'applyOnlyMyTeams'];
        }
    }

    public function applyOnlyMyTeams(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $ta = $mapper->getQueryConverter()->getMainTableAlias();

        $qb->andWhere("($ta.id IN (SELECT entity_id FROM entity_team WHERE deleted=:false AND entity_type=:entityType AND team_id IN (:teamsIds)))")
            ->setParameter('teamsIds', $this->getUser()->getLinkMultipleIdList('teams'), Connection::PARAM_STR_ARRAY)
            ->setParameter('entityType', $this->entityType)
            ->setParameter('false', false, ParameterType::BOOLEAN);
    }

    protected function boolFilterOwnedByMe(array &$result): void
    {
        $field = $this->hasOwnerUserField() ? 'ownerUserId' : 'createdById';

        $result['whereClause'][] = [$field => $this->getUser()->id];
    }

    protected function boolFilterAssignedToMe(array &$result): void
    {
        $field = $this->hasAssignedUserField() ? 'assignedUserId' : 'createdById';

        $result['whereClause'][] = [$field => $this->getUser()->id];
    }

    protected function getRepository(): Repository
    {
        return $this->getEntityManager()->getRepository($this->entityType);
    }

    protected function boolFilterNotParents(array &$result): void
    {
        $id = $this->getBoolFilterParameter('notParents');

        $ids = array_merge([$id], $this->getRepository()->getParentsRecursivelyArray($id));
        $ids = array_merge($ids, array_column($this->getRepository()->get($id)->get('children')->toArray(), 'id'));

        $result['whereClause'][] = ['id!=' => $ids];
    }

    protected function boolFilterNotChildren(array &$result): void
    {
        $id = $this->getBoolFilterParameter('notChildren');

        $ids = array_merge([$id], $this->getRepository()->getChildrenRecursivelyArray($id));
        $ids = array_merge($ids, array_column($this->getRepository()->get($id)->get('parents')->toArray(), 'id'));

        $result['whereClause'][] = ['id!=' => $ids];
    }

    protected function boolFilterOnlyFollowed(array &$result): void
    {
        $result['callbacks'][] = [$this, 'applyOnlyFollowedFilter'];
    }

    public function applyOnlyFollowedFilter(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $table = $mapper->toDb($relEntity->getEntityName());

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
        $qb->innerJoin($tableAlias, 'user_followed_' . $table, 'ufr', "ufr.{$table}_id = $tableAlias.id and ufr.user_id = :ufrUserId and ufr.deleted=:false");
        $qb->setParameter('ufrUserId', $this->getUser()->get('id'));
        $qb->setParameter('false', false, ParameterType::BOOLEAN);
    }

    protected function boolFilterNotEntity(array &$result): void
    {
        foreach ((array)$this->getBoolFilterParameter('notEntity') as $id) {
            $result['whereClause'][] = ['id!=' => $id];
        }
    }

    protected function boolFilterNotAssociatedRecords(array &$result): void
    {
        $data = (array)$this->getBoolFilterParameter('notAssociatedRecords');
        if (empty($data['associationId']) || empty($data['mainRecordId'])) {
            return;
        }

        foreach ($this->getAssociatedRecords($data['associationId'], $data['mainRecordId']) as $id) {
            $result['whereClause'][] = ['id!=' => $id];
        }
    }

    protected function getAssociatedRecords(string $associationId, string $mainRecordId): array
    {
        return $this->getEntityManager()->getDbal()->createQueryBuilder()
            ->select('associated_item_id')
            ->from(Util::toUnderScore("Associated{$this->entityType}"))
            ->where('associating_item_id = :mainRecordId')
            ->andWhere('association_id = :associationId')
            ->andWhere('deleted = :false')
            ->setParameter('associationId', $associationId)
            ->setParameter('mainRecordId', $mainRecordId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();
    }

    protected function getBoolFilterParameter(string $filterName): mixed
    {
        foreach ($this->selectParameters['where'] ?? [] as $row) {
            if (($row['type'] ?? null) === 'bool' && !empty($row['data'][$filterName])) {
                return $row['data'][$filterName];
            }
        }

        return null;
    }

    protected function getSelectManagerFactory(): SelectManagerFactory
    {
        return $this->container->get('selectManagerFactory');
    }

    protected function createSelectManager(string $scope): Base
    {
        return $this->getSelectManagerFactory()->create($scope);
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getUser(): User
    {
        return $this->container->get('user')->get('delegator');
    }

    protected function getAcl(): Acl
    {
        return $this->container->get('acl');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getAclManager(): AclManager
    {
        return $this->container->get('aclManager');
    }

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }

    protected function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getWherePartForClusterId(array $item, ?array $result = null): array
    {
        $entityName      = $this->entityType;
        $suffix          = str_replace('.', '_', uniqid('', true));
        $alias           = 'ci_' . $suffix;
        $entityNameParam = 'cl_en_' . $suffix;
        $mainAlias       = QueryConverter::TABLE_ALIAS;

        $baseCondition = "$alias.entity_name = :$entityNameParam AND $alias.entity_id = $mainAlias.id AND $alias.deleted = :false";
        $params        = [$entityNameParam => $entityName];

        switch ($item['type']) {
            case 'isNull':
                return [
                    'innerSql' => [
                        'sql'        => "NOT EXISTS (SELECT 1 FROM cluster_item $alias WHERE $baseCondition)",
                        'parameters' => $params,
                    ],
                ];
            case 'isNotNull':
                return [
                    'innerSql' => [
                        'sql'        => "EXISTS (SELECT 1 FROM cluster_item $alias WHERE $baseCondition)",
                        'parameters' => $params,
                    ],
                ];
            case 'in':
                $value = $item['value'] ?? null;
                if (empty($value)) {
                    return [];
                }
                $values       = is_array($value) ? $value : [$value];
                $clusterParam = 'cl_ids_' . $suffix;
                return [
                    'innerSql' => [
                        'sql'        => "EXISTS (SELECT 1 FROM cluster_item $alias WHERE $baseCondition AND $alias.cluster_id IN (:$clusterParam))",
                        'parameters' => array_merge($params, [$clusterParam => $values]),
                    ],
                ];
            case 'notIn':
                $value = $item['value'] ?? null;
                if (empty($value)) {
                    return [];
                }
                $values       = is_array($value) ? $value : [$value];
                $clusterParam = 'cl_ids_not_' . $suffix;
                return [
                    'innerSql' => [
                        'sql'        => "NOT EXISTS (SELECT 1 FROM cluster_item $alias WHERE $baseCondition AND $alias.cluster_id IN (:$clusterParam))",
                        'parameters' => array_merge($params, [$clusterParam => $values]),
                    ],
                ];
            default:
                return [];
        }
    }
}
