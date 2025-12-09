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
use Atro\ORM\DB\RDB\Mapper;
use Atro\ORM\DB\RDB\Query\QueryConverter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Acl;
use Espo\Core\AclManager;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Core\ORM\Entity;
use Espo\Core\ORM\Repositories\RDB;
use Espo\Core\SelectManagerFactory;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\Entities\User;
use Espo\ORM\EntityManager;
use Espo\ORM\IEntity;

class Base
{
    const MIN_LENGTH_FOR_CONTENT_SEARCH = 4;

    protected User $user;

    protected Acl $acl;

    protected EntityManager $entityManager;

    protected string $entityType;

    protected Metadata $metadata;

    protected array $selectParameters;

    protected Config $config;

    protected ?SelectManagerFactory $selectManagerFactory;

    protected ?IEntity $seed = null;

    public bool $isSubQuery = false;

    protected array $textFilterUseContainsAttributeList = [];

    public function __construct(EntityManager $entityManager, User $user, Acl $acl, AclManager $aclManager, Metadata $metadata, Config $config, InjectableFactory $injectableFactory)
    {
        $this->entityManager = $entityManager;
        $this->user = $user;
        $this->acl = $acl;
        $this->aclManager = $aclManager;
        $this->metadata = $metadata;
        $this->config = $config;
        $this->injectableFactory = $injectableFactory;
    }

    protected function limit($offset = null, $maxSize = null, &$result = [])
    {
        if (!is_null($offset)) {
            $result['offset'] = $offset;
        }
        if (!is_null($maxSize)) {
            $result['limit'] = $maxSize;
        }
    }

    protected function order($sortBy, $desc = false, &$result = [])
    {
        if (!empty($sortBy)) {

            $result['orderBy'] = $sortBy;
            $type = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $sortBy, 'type']);
            if (in_array($type, ['link', 'file', 'image'])) {
                $result['orderBy'] .= 'Name';
            } else {
                if ($type === 'linkParent') {
                    $result['orderBy'] .= 'Type';
                } else {
                    if ($type === 'address') {
                        if (!$desc) {
                            $orderPart = 'ASC';
                        } else {
                            $orderPart = 'DESC';
                        }
                        $result['orderBy'] = [[$sortBy . 'Country', $orderPart], [$sortBy . 'City', $orderPart], [$sortBy . 'Street', $orderPart]];
                        return;
                    }
                }
            }
        }
        if (!$desc) {
            $result['order'] = 'ASC';
        } else {
            $result['order'] = 'DESC';
        }
    }

    protected function getTextFilterFieldList()
    {
        $result = ['id'];

        $fields = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []);
        $textFilterFields = $this->getMetadata()->get(['scopes', $this->entityType, 'textFilterFields'], []);
        foreach ($fields as $field => $fieldDefs) {
            if (!empty($fieldDefs['notStorable']) || !empty($fieldDefs['filterDisabled'])) {
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

    protected function getSeed()
    {
        if (empty($this->seed)) {
            $this->seed = $this->getEntityManager()->getEntityFactory()->create($this->entityType);
            $this->seed->setIsNew(true);
        }

        return $this->seed;
    }

    public function applyWhere($where, &$result)
    {
        $this->prepareResult($result);
        $this->where($where, $result);
    }

    protected function where($where, &$result)
    {
        $this->prepareResult($result);

        foreach ($where as $item) {
            if (!isset($item['type'])) {
                continue;
            }

            if ($item['type'] == 'bool' && !empty($item['value']) && is_array($item['value'])) {
                foreach ($item['value'] as $filter) {
                    $p = $this->getBoolFilterWhere($filter);
                    if (!empty($p)) {
                        $where[] = $p;
                    }
                    $this->applyBoolFilter($filter, $result);
                }
            } else {
                if ($item['type'] == 'textFilter') {
                    if (isset($item['value']) || $item['value'] !== '') {
                        $this->textFilter($item['value'], $result);
                    }
                } else {
                    if ($item['type'] == 'primary' && !empty($item['value'])) {
                        $this->applyPrimaryFilter($item['value'], $result);
                    }
                }
            }
        }

        $whereClause = $this->convertWhere($where, false, $result);

        $result['whereClause'] = array_merge($result['whereClause'], $whereClause);
    }

    public function convertWhere(array $where, $ignoreAdditionaFilterTypes = false, &$result = null)
    {
        $whereClause = [];

        $ignoreTypeList = ['bool', 'primary'];

        foreach ($where as $item) {
            if (!isset($item['type'])) {
                continue;
            }

            $type = $item['type'];
            if (!in_array($type, $ignoreTypeList)) {
                $part = $this->getWherePart($item, $result);
                if (!empty($part)) {
                    $whereClause[] = $part;
                }
            } else {
                if (!$ignoreAdditionaFilterTypes) {
                    if (!empty($item['value'])) {
                        $methodName = 'apply' . ucfirst($type);

                        if (method_exists($this, $methodName)) {
                            $attribute = null;
                            if (isset($item['field'])) {
                                $attribute = $item['field'];
                            }
                            if (isset($item['attribute'])) {
                                $attribute = $item['attribute'];
                            }
                            if ($attribute) {
                                $this->$methodName($attribute, $item['value'], $result);
                            }
                        }
                    }
                }
            }
        }

        return $whereClause;
    }

    public function mutateWhereQuery(array &$where, &$result = null): void
    {
        foreach ($where as &$item) {
            if (isset($item['rules'])) {
                $this->mutateWhereQuery($item['rules'], $result);
                $item = ['type' => $this->qbConditionToType((string)$item['condition']), 'value' => $item['rules']];
            } else {
                // for attributes
                $additionForAttribute = [];
                $attribute = "";
                if (!empty($item['id'])) {
                    $attribute = $item['id'];
                    if (strpos($item['id'], 'attr_') !== false) {
                        $parts = explode('_', $item['id']);
                        $additionForAttribute['attribute'] = $result['attributesIds'][] = $parts[1];
                        $additionForAttribute['isAttribute'] = true;

                        if (!empty($parts[2])) {
                            if ($parts[2] === 'main') {
                                $additionForAttribute['language'] = 'main';
                            } else {
                                $language = Util::toUnderScore($parts[2]);
                                $language = explode('_', $language);
                                if (count($language) === 2) {
                                    $language = $language[0] . '_' . strtoupper($language[1]);
                                    $additionForAttribute['language'] = $language;
                                }
                            }
                        }
                    }
                }

                if (!empty($item['data']['subQuery']) && !empty($item['operator']) && !empty($item['id'])) {
                    $value = [];
                    $type = $this->qbOperatorToType((string)$item['operator']);
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
                        $item = [
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
                        $item = [
                            'attribute' => $attribute,
                            'type'      => $isTrueCondition ? 'isTrue' : 'isFalse',
                        ];
                    } else if (in_array($type, ['is_me', 'is_not_me', 'is_team_member', 'include_me', 'exclude_me', 'is_my_team', 'is_not_my_team'])) {
                        $seed = $this->getSeed();
                        $link = substr($item['id'], -2) === 'Id' ? substr($item['id'], 0, -2) : $item['id'];
                        $entity = $seed->getRelationParam($link, 'entity');
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
                        $item = [
                            'attribute' => $attribute,
                            'type'      => $type,
                            'value'     => $item['value'],
                        ];
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

    public function qbConditionToType(string $condition): string
    {
        return strtolower($condition) === 'or' ? 'or' : 'and';
    }

    public function qbOperatorToType(string $operator): string
    {
        switch ($operator) {
            case 'equal':
                $operator = 'equals';
                break;
            case 'not_equal':
                $operator = 'notEquals';
                break;
            case 'begins_with':
                $operator = 'startsWith';
                break;
            case 'ends_with':
                $operator = 'endsWith';
                break;
            case 'not_contains':
                $operator = 'notContains';
                break;
            case 'less':
                $operator = 'lessThan';
                break;
            case 'less_or_equal':
                $operator = 'lessThanOrEquals';
                break;
            case 'greater':
                $operator = 'greaterThan';
                break;
            case 'greater_or_equal':
                $operator = 'greaterThanOrEquals';
                break;
            case 'not_in':
                $operator = 'notIn';
                break;
            case 'linked_with':
                $operator = 'linkedWith';
                break;
            case 'not_linked_with':
                $operator = 'notLinkedWith';
                break;
            case 'is_attribute_linked':
            case 'is_linked':
                $operator = 'isLinked';
                break;
            case 'is_attribute_not_linked':
            case 'is_not_linked':
                $operator = 'isNotLinked';
                break;
            case 'is_null':
                $operator = 'isNull';
                break;
            case 'is_not_null':
                $operator = 'isNotNull';
                break;
            case 'array_any_of':
                $operator = 'arrayAnyOf';
                break;
            case 'array_none_of':
                $operator = 'arrayNoneOf';
                break;
            case 'last_x_days':
                $operator = 'lastXDays';
                break;
            case 'next_x_days':
                $operator = 'nextXDays';
                break;
            case 'current_month':
                $operator = 'currentMonth';
                break;
            case 'last_month':
                $operator = 'lastMonth';
                break;
            case 'next_month':
                $operator = 'nextMonth';
                break;
            case 'current_year':
                $operator = 'currentYear';
                break;
            case 'last_year':
                $operator = 'lastYear';
                break;
        }

        return $operator;
    }

    protected function applyLinkedWith($link, $idsValue, &$result)
    {
        $part = array();

        if (is_array($idsValue) && count($idsValue) == 1) {
            $idsValue = $idsValue[0];
        }

        $seed = $this->getSeed();

        if (!$seed->hasRelation($link)) {
            return;
        }

        $relDefs = $this->getSeed()->getRelations();

        $relationType = $seed->getRelationType($link);

        $defs = $relDefs[$link];
        if ($relationType == 'manyMany') {
            $this->addLeftJoin([$link, $link . 'Filter'], $result);
            $midKeys = $seed->getRelationParam($link, 'midKeys');

            if (!empty($midKeys)) {
                $key = $midKeys[1];
                $part[$link . 'Filter' . 'Middle.' . $key] = $idsValue;
            }
        } else {
            if ($relationType == 'hasMany') {
                $alias = $link . 'Filter';
                $this->addLeftJoin([$link, $alias], $result);

                $part[$alias . '.id'] = $idsValue;
            } else {
                if ($relationType == 'belongsTo') {
                    $key = $seed->getRelationParam($link, 'key');
                    if (!empty($key)) {
                        $part[$key] = $idsValue;
                    }
                } else {
                    if ($relationType == 'hasOne') {
                        $this->addJoin([$link, $link . 'Filter'], $result);
                        $part[$link . 'Filter' . '.id'] = $idsValue;
                    } else {
                        return;
                    }
                }
            }
        }

        if (!empty($part)) {
            $result['whereClause'][] = $part;
        }

        $this->setDistinct(true, $result);
    }

    protected function q($params, &$result)
    {
        if (isset($params['q']) && $params['q'] !== '') {
            $textFilter = $params['q'];
            $this->textFilter($textFilter, $result);
        }
    }

    public function manageAccess(&$result)
    {
        $this->prepareResult($result);
        $this->applyAccess($result);
    }

    public function manageTextFilter($textFilter, &$result)
    {
        $this->prepareResult($result);
        $this->q(['q' => $textFilter], $result);
    }

    public function getEmptySelectParams()
    {
        $result = array();
        $this->prepareResult($result);

        return $result;
    }

    protected function prepareResult(&$result)
    {
        if (empty($result)) {
            $result = array();
        }
        if (empty($result['joins'])) {
            $result['joins'] = [];
        }
        if (empty($result['leftJoins'])) {
            $result['leftJoins'] = [];
        }
        if (empty($result['whereClause'])) {
            $result['whereClause'] = array();
        }
        if (empty($result['customJoin'])) {
            $result['customJoin'] = '';
        }
        if (empty($result['additionalSelectColumns'])) {
            $result['additionalSelectColumns'] = array();
        }
        if (empty($result['joinConditions'])) {
            $result['joinConditions'] = array();
        }
        if (!isset($result['customWhere'])) {
            $result['customWhere'] = '';
        }
    }

    protected function access(&$result)
    {
        if (!$this->getUser()->isAdmin() && $this->getMetadata()->get(['scopes', $this->getEntityType(), 'type'], 'Base') == 'Relation') {
            $result['callbacks'][] = [$this, 'accessForRelationEntity'];
        }

        if ($this->getAcl()->checkReadOnlyOwn($this->getEntityType())) {
            $this->accessOnlyOwn($result);
        } else {
            if (!$this->getUser()->isAdmin()) {
                if ($this->getAcl()->checkReadOnlyTeam($this->getEntityType())) {
                    $this->accessOnlyTeam($result);
                } else {
                    if ($this->getAcl()->checkReadNo($this->getEntityType())) {
                        $this->accessNo($result);
                    }
                }
            }
        }
    }

    protected function accessNo(&$result)
    {
        $result['whereClause'][] = array(
            'id' => null
        );
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

                $sp = $this->createSelectManager($entityName)->getSelectParams([], true, true);
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

    protected function accessOnlyOwn(&$result)
    {
        if ($this->hasOwnerUserField()) {
            $d['ownerUserId'] = $this->getUser()->id;
        }
        if ($this->hasAssignedUserField()) {
            $d['assignedUserId'] = $this->getUser()->id;
        }
        if ($this->hasCreatedByField() && !$this->hasAssignedUserField() && !$this->hasOwnerUserField()) {
            $d['createdById'] = $this->getUser()->id;
        }

        $result['whereClause'][] = ['OR' => $d];
    }

    protected function accessOnlyTeam(&$result)
    {
        $this->setDistinct(true, $result);

        $result['callbacks'][] = [$this, 'applyAccessOnlyTeam'];
    }

    public function applyAccessOnlyTeam(QueryBuilder $qb, $entity, $params, Mapper $mapper): void
    {
        $currentUserId = $this->getUser()->id;

        $ta = $mapper->getQueryConverter()->getMainTableAlias();

        $parts = [];
        if ($this->hasTeamsField()) {
            $parts[] = "($ta.id IN (SELECT entity_id FROM entity_team WHERE deleted=:false AND entity_type=:entityType AND team_id IN (:teamsIds)))";
            $qb->setParameter('teamsIds', $this->getUser()->getLinkMultipleIdList('teams'), Connection::PARAM_STR_ARRAY);
            $qb->setParameter('entityType', $this->entityType);
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
            $qb->andWhere(join(' OR ', $parts));
        }
    }

    /**
     * @deprecated will be removed soon
     */
    protected function hasAssignedUsersField()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function hasOwnerUserField()
    {
        return !empty($this->getMetadata()->get('scopes.' . $this->getEntityType() . '.hasOwner'));
    }

    /**
     * @return bool
     */
    protected function hasAssignedUserField()
    {
        return !empty($this->getMetadata()->get('scopes.' . $this->getEntityType() . '.hasAssignedUser'));
    }

    protected function boolFilterFieldsFilter(array &$result): void
    {
        $data = $this->getBoolFilterParameter('fieldsFilter') ?? [];
        if (!empty($data)) {
            foreach ($data as $field => $value) {
                $result['whereClause'][] = [$field => $value];
            }
        }
    }

    /**
     * @param array $result
     *
     * @return void
     */
    protected function boolFilterOnlyActive(array &$result): void
    {
        $result['whereClause'][] = [
            'isActive' => true
        ];
    }

    protected function boolFilterOnlyArchived(array &$result): void
    {
        $result['whereClause'][] = [
            'isArchived' => true
        ];
    }

    protected function boolFilterWithArchived(array &$result): void
    {
        $result['withArchived'] = true;
    }

    protected function boolFilterOnlyDeleted(array &$result): void
    {
        $result['withDeleted'] = true;
        $result['whereClause'][] = [
            "deleted" => true
        ];
        $result['additionalSelectColumns'][QueryConverter::TABLE_ALIAS . ".deleted"] = "deleted";
    }

    protected function boolFilterNotActive(array &$result): void
    {
        $result['whereClause'][] = [
            'isActive' => false
        ];
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

        $handler = $this->getEntityManager()->getContainer()->get($reportHandlerClass);

        $methodName = 'filteringBy' . ucfirst($data['field']);
        if (method_exists($handler, $methodName)) {
            $result['reportSpecific'] = $data;
            $result['callbacks'][] = [$handler, $methodName];
        }
    }

    public function applyOnlyBookmarked(QueryBuilder $qb, $entity, $params, Mapper $mapper): void
    {
        $currentUserId = $this->getUser()->id;

        $ta = $mapper->getQueryConverter()->getMainTableAlias();

        $qb->andWhere("$ta.id IN (SELECT entity_id FROM bookmark WHERE entity_type = :bentityType AND user_id = :bUserId AND deleted = :false)")
            ->setParameter('bentityType', $entity->getEntityType())
            ->setParameter('bUserId', $currentUserId)
            ->setParameter('false', false, ParameterType::BOOLEAN);
    }

    public function applyMultipleClassifications(QueryBuilder $qb, $entity, $params, Mapper $mapper): void
    {
        $cqb = $this->getEntityManager()->getRepository('Classification')->getMultipleClassificationsQb($entity->getEntityType());
        $mainAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $qb->andWhere($qb->expr()->in("$mainAlias.id", $cqb->getSQL()));
    }

    /**
     * @return bool
     */
    protected function hasCreatedByField()
    {
        return $this->getSeed()->hasAttribute('createdById');
    }

    /**
     * @return bool
     */
    protected function hasTeamsField()
    {
        return $this->getSeed()->hasRelation('teams') && $this->getSeed()->hasAttribute('teamsIds');
    }

    public function getAclParams()
    {
        $result = array();
        $this->applyAccess($result);
        return $result;
    }

    public function buildSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        return $this->getSelectParams($params, $withAcl, $checkWherePermission);
    }

    public function dispatch(string $scope, string $action, Event $event): Event
    {
        return $this->getMetadata()->getEventManager()->dispatch($scope, $action, $event);
    }

    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        $params = $this
            ->dispatch('Entity', 'beforeGetSelectParams', new Event(['params' => $params, 'entityType' => $this->entityType]))
            ->getArgument('params');

        $this->selectParameters = $params;

        $this->prepareForExtensibleEnumOption($params);

        $result = array();
        $this->prepareResult($result);

        if (!empty($params['sortBy'])) {
            if (!array_key_exists('asc', $params)) {
                $params['asc'] = true;
            }
            $this->order($params['sortBy'], !$params['asc'], $result);
        }

        if (!isset($params['offset'])) {
            $params['offset'] = null;
        }
        if (!isset($params['maxSize'])) {
            $params['maxSize'] = null;
        }
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
                $this->applyFilter($filterName, $result);
            }
        }

        if (!empty($params['where']) && is_array($params['where'])) {
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

        // check if entity has hasArchive activated
        if ($this->metadata->get(['scopes', $this->entityType, 'hasArchive'])) {
            //filter only if boolean filter not activated
            $hasArchivedFilterInWhere = count(
                    array_filter($result['whereClause'], function ($row) {
                        return isset($row['isArchived=']) || isset($row['isArchived']);
                    })
                ) > 0;

            if (!isset($result['withArchived']) && !$hasArchivedFilterInWhere) {
                $result['whereClause'][] = [
                    'isArchived' => false
                ];

            }
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

    protected function prepareForExtensibleEnumOption(array &$params): void
    {
        if ($this->entityType !== 'ExtensibleEnumOption' || empty($params['where'])) {
            return;
        }

        foreach ($params['where'] ?? [] as $filter) {
            if (!empty($filter['type']) && $filter['type'] === 'bool' && !empty($filter['value'])) {
                foreach ($filter['value'] as $boolFilter) {
                    $method = "boolFilter" . ucfirst($boolFilter);
                    if (method_exists($this, $method)) {
                        $this->$method($params, isset($filter['data'][$boolFilter]) ? $filter['data'][$boolFilter] : null);
                    }
                }
            }
        }
    }

    protected function boolFilterOnlyExtensibleEnumOptionIds(&$params, ?array $ids = [])
    {
        if (!is_array($ids) || empty($ids)) {
            return $params;
        }

        $params['where'][] = [
            "type"      => "notIn",
            "attribute" => "id",
            "value"     => $ids
        ];

        return $params;
    }

    protected function checkWhere($where)
    {
        foreach ($where as $w) {
            $attribute = null;
            if (isset($w['field'])) {
                $attribute = $w['field'];
            }
            if (isset($w['attribute'])) {
                $attribute = $w['attribute'];
            }
            if ($attribute) {
                if (isset($w['type']) && in_array($w['type'], ['isLinked', 'isNotLinked', 'linkedWith', 'notLinkedWith'])) {
                    if (in_array($attribute, $this->getAcl()->getScopeForbiddenFieldList($this->getEntityType()))) {
                        throw new Forbidden();
                    }
                } else {
                    if (in_array($attribute, $this->getAcl()->getScopeForbiddenAttributeList($this->getEntityType()))) {
                        throw new Forbidden();
                    }
                }
            }
            if (!empty($w['value']) && is_array($w['value'])) {
                $this->checkWhere($w['value']);
            }
        }
    }

    public function convertDateTimeWhere($item)
    {
        $format = 'Y-m-d H:i:s';

        $value = null;
        $timeZone = 'UTC';

        $attribute = null;
        if (isset($item['field'])) {
            $attribute = $item['field'];
        }
        if (isset($item['attribute'])) {
            $attribute = $item['attribute'];
        }

        if (!$attribute) {
            return null;
        }
        if (empty($item['type'])) {
            return null;
        }
        if (!empty($item['value'])) {
            $value = $item['value'];
        }

        if (!empty($item['timeZone'])) {
            $timeZone = $item['timeZone'];
        }

        $type = $item['type'];

        if (empty($value) && in_array($type, array('on', 'before', 'after'))) {
            return null;
        }

        $where = array();
        $where['attribute'] = $attribute;

        $dt = new \DateTime('now', new \DateTimeZone($timeZone));

        switch ($type) {
            case 'today':
                $where['type'] = 'between';
                $dt->setTime(0, 0, 0);
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $from = $dt->format($format);
                $dt->modify('+1 day -1 second');
                $to = $dt->format($format);
                $where['value'] = [$from, $to];
                break;
            case 'past':
                $where['type'] = 'before';
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format($format);
                break;
            case 'future':
                $where['type'] = 'after';
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format($format);
                break;
            case 'lastSevenDays':
                $where['type'] = 'between';

                $dtFrom = clone $dt;

                $dt->setTimezone(new \DateTimeZone('UTC'));
                $to = $dt->format($format);


                $dtFrom->modify('-7 day');
                $dtFrom->setTime(0, 0, 0);
                $dtFrom->setTimezone(new \DateTimeZone('UTC'));

                $from = $dtFrom->format($format);

                $where['value'] = [$from, $to];

                break;
            case 'lastXDays':
                $where['type'] = 'between';

                $dtFrom = clone $dt;

                $dt->setTimezone(new \DateTimeZone('UTC'));
                $to = $dt->format($format);

                $number = strval(intval($item['value']));
                $dtFrom->modify('-' . $number . ' day');
                $dtFrom->setTime(0, 0, 0);
                $dtFrom->setTimezone(new \DateTimeZone('UTC'));

                $from = $dtFrom->format($format);

                $where['value'] = [$from, $to];

                break;
            case 'nextXDays':
                $where['type'] = 'between';

                $dtTo = clone $dt;

                $dt->setTimezone(new \DateTimeZone('UTC'));
                $from = $dt->format($format);

                $number = strval(intval($item['value']));
                $dtTo->modify('+' . $number . ' day');
                $dtTo->setTime(24, 59, 59);
                $dtTo->setTimezone(new \DateTimeZone('UTC'));

                $to = $dtTo->format($format);

                $where['value'] = [$from, $to];

                break;
            case 'olderThanXDays':
                $where['type'] = 'before';
                $number = strval(intval($item['value']));
                $dt->modify('-' . $number . ' day');
                $dt->setTime(0, 0, 0);
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format($format);
                break;
            case 'afterXDays':
                $where['type'] = 'after';
                $number = strval(intval($item['value']));
                $dt->modify('+' . $number . ' day');
                $dt->setTime(0, 0, 0);
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format($format);
                break;
            case 'on':
                $where['type'] = 'between';

                $dt = new \DateTime($value, new \DateTimeZone($timeZone));
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $from = $dt->format($format);
                $dt->modify('+1 day -1 second');
                $to = $dt->format($format);
                $where['value'] = [$from, $to];
                break;
            case 'before':
                $where['type'] = 'before';
                $dt = new \DateTime($value, new \DateTimeZone($timeZone));
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format($format);
                break;
            case 'after':
                $where['type'] = 'after';
                $dt = new \DateTime($value, new \DateTimeZone($timeZone));
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format($format);
                break;
            case 'between':
                $where['type'] = 'between';
                if (is_array($value)) {
                    $dt = new \DateTime($value[0], new \DateTimeZone($timeZone));
                    $dt->setTimezone(new \DateTimeZone('UTC'));
                    $from = $dt->format($format);

                    $dt = new \DateTime($value[1], new \DateTimeZone($timeZone));
                    $dt->setTimezone(new \DateTimeZone('UTC'));
                    $dt->modify('-1 second');
                    $to = $dt->format($format);

                    $where['value'] = [$from, $to];
                }
                break;
            default:
                $where['type'] = $type;
                if(!empty($value)) {
                    $dt = new \DateTime($value, new \DateTimeZone($timeZone));
                    $dt->setTimezone(new \DateTimeZone('UTC'));
                    $where['value'] = $dt->format($format);
                }

        }

        $where['dateTime'] = false;
        $result = $this->getWherePart($where);

        return $result;
    }

    protected function modifyPartForHierarchy(array &$item): void
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

    protected function getWherePart($item, &$result = null)
    {
        $part = [];

        $entityType = $this->getMetadata()->get(['scopes', $this->entityType, 'type']);

        $method = "modifyPartFor{$entityType}";
        if (method_exists($this, $method)) {
            $this->$method($item);
        }

        $attribute = null;
        if (!empty($item['field'])) { // for backward compatibility
            $attribute = $item['field'];
        }
        if (!empty($item['attribute'])) {
            $attribute = $item['attribute'];
        }

        if (!is_null($attribute) && !is_string($attribute)) {
            throw new Error('Bad attribute in where statement');
        }

        if (
            !isset($item['dateTime'])
            && !empty($attribute)
            && $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $attribute, 'type']) === 'datetime'
        ) {
            $item['dateTime'] = true;
        }

        if (!empty($item['isAttribute']) && $this->getMetadata()->get(['scopes', $this->entityType, 'hasAttribute'])) {
            unset($item['isAttribute']);
            $this->getEntityManager()->getAttributeFieldConverter()->getWherePart($this->getSeed(), $item, $result);
        }

        if (!empty($item['subQuery'])) {
            $link = substr($attribute, -2) === 'Id' ? substr($attribute, 0, -2) : $attribute;
            $foreignEntity = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $link, 'entity']) ?? $item['foreignEntity'] ?? null;
            $foreignField = $item['foreignField'] ?? 'id';
            if (!empty($foreignEntity)) {
                $foreignRepository = $this->getEntityManager()->getRepository($foreignEntity);
                $sp = $this->createSelectManager($foreignEntity)->getSelectParams(['where' => $item['subQuery']], true, true);
                $sp['select'] = [$foreignField];
                $qb1 = $foreignRepository->getMapper()->createSelectQueryBuilder($foreignRepository->get(), $sp, true);
                $item['value'] = [
                    "innerSql" => [
                        "sql"        => str_replace($this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias(), 'sbq_' . Util::generateId(), $qb1->getSql()),
                        "parameters" => $qb1->getParameters()
                    ]
                ];
            }
            unset($item['subQuery']);
        }

        if (!empty($attribute) && !empty($item['type'])) {
            $methodName = 'getWherePart' . ucfirst($attribute) . ucfirst($item['type']);
            if (method_exists($this, $methodName)) {
                $value = null;
                if (array_key_exists('value', $item)) {
                    $value = $item['value'];
                }
                return $this->$methodName($value, $result);
            }
        }

        if (!empty($item['dateTime'])) {
            return $this->convertDateTimeWhere($item);
        }

        if (!array_key_exists('value', $item)) {
            $item['value'] = null;
        }
        $value = $item['value'];

        if (!empty($item['type'])) {
            $type = $item['type'];

            switch ($type) {
                case 'innerSql':
                    $part['innerSql'] = $item['value'];
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

                case 'like':
                    $part[$attribute . '*'] = $value;
                    break;

                case 'notLike':
                    $part[$attribute . '!*'] = $value;
                    break;

                case 'equals':
                case 'on':
                    $part[$attribute . '='] = $value;
                    break;

                case 'startsWith':
                    $part[$attribute . '*'] = $value . '%';
                    break;

                case 'endsWith':
                    $part[$attribute . '*'] = '%' . $value;
                    break;

                case 'contains':
                    $part[$attribute . '*'] = '%' . $value . '%';
                    break;

                case 'notContains':
                    $part[$attribute . '!*'] = '%' . $value . '%';
                    break;

                case 'notEquals':
                case 'notOn':
                    $part[$attribute . '!='] = $value;
                    break;

                case 'greaterThan':
                case 'after':
                    $part[$attribute . '>'] = $value;
                    break;

                case 'lessThan':
                case 'before':
                    $part[$attribute . '<'] = $value;
                    break;

                case 'greaterThanOrEquals':
                    $part[$attribute . '>='] = $value;
                    break;

                case 'lessThanOrEquals':
                    $part[$attribute . '<='] = $value;
                    break;

                case 'in':
                    if (!empty($item['attribute'])) {
                        $hasEmpty = is_array($value) && in_array('', $value);
                        $value = $this->prepareValueOptions($value, $item['attribute']);
                        if ($hasEmpty) {
                            $value[] = '';
                        }
                    }
                    $part[$attribute . '='] = $value;
                    break;

                case 'notIn':
                    if (!empty($item['attribute'])) {
                        $hasEmpty = is_array($value) && in_array('', $value);
                        $value = $this->prepareValueOptions($value, $item['attribute']);
                        if ($hasEmpty) {
                            $value[] = '';
                        }
                    }
                    $part[$attribute . '!='] = $value;
                    break;

                case 'isNull':
                    $part[$attribute . '='] = null;
                    break;

                case 'isNotNull':
                case 'ever':
                    $part[$attribute . '!='] = null;
                    break;

                case 'isTrue':
                    $part[$attribute . '='] = true;
                    break;

                case 'isFalse':
                    $part[$attribute . '='] = false;
                    break;

                case 'today':
                    $part[$attribute . '='] = date('Y-m-d');
                    break;

                case 'past':
                    $part[$attribute . '<'] = date('Y-m-d');
                    break;

                case 'future':
                    $part[$attribute . '>='] = date('Y-m-d');
                    break;

                case 'lastSevenDays':
                    $dt1 = new \DateTime();
                    $dt2 = clone $dt1;
                    $dt2->modify('-7 days');
                    $part['AND'] = [
                        $attribute . '>=' => $dt2->format('Y-m-d'),
                        $attribute . '<=' => $dt1->format('Y-m-d'),
                    ];
                    break;

                case 'lastXDays':
                    $dt1 = new \DateTime();
                    $dt2 = clone $dt1;
                    $number = strval(intval($value));

                    $dt2->modify('-' . $number . ' days');
                    $dt2->setTime(0, 0, 0);
                    $dt1->setTime(23, 59, 59);
                    $part['AND'] = [
                        $attribute . '>=' => $dt2->format('Y-m-d H:i:s'),
                        $attribute . '<=' => $dt1->format('Y-m-d H:i:s'),
                    ];
                    break;

                case 'nextXDays':
                    $dt1 = new \DateTime();
                    $dt2 = clone $dt1;
                    $number = strval(intval($value));
                    $dt2->modify('+' . $number . ' days');
                    $dt1->setTime(0, 0, 0);
                    $dt2->setTime(23, 59, 59);
                    $part['AND'] = [
                        $attribute . '>=' => $dt1->format('Y-m-d H:i:s'),
                        $attribute . '<=' => $dt2->format('Y-m-d H:i:s'),
                    ];
                    break;

                case 'olderThanXDays':
                    $dt1 = new \DateTime();
                    $number = strval(intval($value));
                    $dt1->modify('-' . $number . ' days');
                    $part[$attribute . '<'] = $dt1->format('Y-m-d');
                    break;

                case 'afterXDays':
                    $dt1 = new \DateTime();
                    $number = strval(intval($value));
                    $dt1->modify('+' . $number . ' days');
                    $part[$attribute . '>'] = $dt1->format('Y-m-d');
                    break;

                case 'currentMonth':
                    $dt = new \DateTime();
                    $part['AND'] = [
                        $attribute . '>=' => $dt->modify('first day of this month')->format('Y-m-d'),
                        $attribute . '<'  => $dt->add(new \DateInterval('P1M'))->format('Y-m-d'),
                    ];
                    break;

                case 'lastMonth':
                    $dt = new \DateTime();
                    $part['AND'] = [
                        $attribute . '>=' => $dt->modify('first day of last month')->format('Y-m-d'),
                        $attribute . '<'  => $dt->add(new \DateInterval('P1M'))->format('Y-m-d'),
                    ];
                    break;

                case 'nextMonth':
                    $dt = new \DateTime();
                    $part['AND'] = [
                        $attribute . '>=' => $dt->modify('first day of next month')->format('Y-m-d'),
                        $attribute . '<'  => $dt->add(new \DateInterval('P1M'))->format('Y-m-d'),
                    ];
                    break;

                case 'currentQuarter':
                    $dt = new \DateTime();
                    $quarter = ceil($dt->format('m') / 3);
                    $dt->modify('first day of January this year');
                    $part['AND'] = [
                        $attribute . '>=' => $dt->add(new \DateInterval('P' . (($quarter - 1) * 3) . 'M'))->format('Y-m-d'),
                        $attribute . '<'  => $dt->add(new \DateInterval('P3M'))->format('Y-m-d'),
                    ];
                    break;

                case 'lastQuarter':
                    $dt = new \DateTime();
                    $quarter = ceil($dt->format('m') / 3);
                    $dt->modify('first day of January this year');
                    $quarter--;
                    if ($quarter == 0) {
                        $quarter = 4;
                        $dt->modify('-1 year');
                    }
                    $part['AND'] = [
                        $attribute . '>=' => $dt->add(new \DateInterval('P' . (($quarter - 1) * 3) . 'M'))->format('Y-m-d'),
                        $attribute . '<'  => $dt->add(new \DateInterval('P3M'))->format('Y-m-d'),
                    ];
                    break;

                case 'currentYear':
                    $dt = new \DateTime();
                    $part['AND'] = [
                        $attribute . '>=' => $dt->modify('first day of January this year')->format('Y-m-d'),
                        $attribute . '<'  => $dt->add(new \DateInterval('P1Y'))->format('Y-m-d'),
                    ];
                    break;

                case 'lastYear':
                    $dt = new \DateTime();
                    $part['AND'] = [
                        $attribute . '>=' => $dt->modify('first day of January last year')->format('Y-m-d'),
                        $attribute . '<'  => $dt->add(new \DateInterval('P1Y'))->format('Y-m-d'),
                    ];
                    break;

                case 'between':
                    if (is_array($value)) {
                        $part['AND'] = [
                            $attribute . '>=' => $value[0],
                            $attribute . '<=' => $value[1],
                        ];
                    }
                    break;

                case 'columnLike':
                case 'columnIn':
                case 'columnIsNull':
                case 'columnNotIn':
                    $link = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $attribute, 'link']);
                    $column = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $attribute, 'column']);
                    $alias = $link . 'Filter' . strval(rand(10000, 99999));
                    $this->setDistinct(true, $result);
                    $this->addLeftJoin([$link, $alias], $result);
                    $columnKey = $alias . 'Middle.' . $column;
                    if ($type === 'columnIn') {
                        $part[$columnKey] = $value;
                    } else {
                        if ($type === 'columnNotIn') {
                            $part[$columnKey . '!='] = $value;
                        } else {
                            if ($type === 'columnIsNull') {
                                $part[$columnKey] = null;
                            } else {
                                if ($type === 'columnIsNotNull') {
                                    $part[$columnKey . '!='] = null;
                                } else {
                                    if ($type === 'columnLike') {
                                        $part[$columnKey . '*'] = $value;
                                    } else {
                                        if ($type === 'columnStartsWith') {
                                            $part[$columnKey . '*'] = $value . '%';
                                        } else {
                                            if ($type === 'columnEndsWith') {
                                                $part[$columnKey . '*'] = '%' . $value;
                                            } else {
                                                if ($type === 'columnContains') {
                                                    $part[$columnKey . '*'] = '%' . $value . '%';
                                                } else {
                                                    if ($type === 'columnEquals') {
                                                        $part[$columnKey . '='] = $value;
                                                    } else {
                                                        if ($type === 'columnNotEquals') {
                                                            $part[$columnKey . '!='] = $value;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;

                case 'isNotLinked':
                    if (!$result) {
                        break;
                    }

                    $link = $attribute;
                    /** @var Entity $seed */
                    $seed = $this->getSeed();
                    $relationType = $seed->getRelationType($link);

                    if ($relationType === 'manyMany') {
                        $connection = $this->getEntityManager()->getConnection();
                        $ta = $this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias();
                        $alias = $attribute . 'IsNotLinkedFilter' . strval(rand(10000, 99999));
                        $aliasMiddle = $alias . 'Middle';
                        $relationTable = $seed->getRelationParam($attribute, 'relationName');
                        $foreignTable = $seed->getRelationParam($attribute, 'entity');
                        $midKeys = $seed->getRelationParam($attribute, 'midKeys');

                        if (empty($relationTable) || empty($foreignTable) || empty($midKeys) || count($midKeys) < 2) {
                            break;
                        }

                        $relationTable = $connection->quoteIdentifier(Util::toUnderScore($relationTable));
                        $foreignTable = $connection->quoteIdentifier(Util::toUnderScore($foreignTable));
                        $midKeys = [Util::toUnderScore($midKeys[0]), Util::toUnderScore($midKeys[1])];

                        $subQb = $connection->createQueryBuilder()
                            ->select('1')
                            ->from($relationTable, $aliasMiddle)
                            ->join($aliasMiddle, $foreignTable, $alias, "$aliasMiddle.{$midKeys[1]} = $alias.id AND $alias.deleted = :false")
                            ->where("$aliasMiddle.$midKeys[0] = $ta.id")
                            ->andWhere("$aliasMiddle.deleted = :false");

                        $part['innerSql'] = [
                            'sql'        => 'NOT EXISTS (' . $subQb->getSQL() . ')',
                            'parameters' => $subQb->getParameters(),
                        ];
                    } else {
                        $alias = $attribute . 'IsNotLinkedFilter' . strval(rand(10000, 99999));
                        $part[$alias . '.id'] = null;
                        $this->setDistinct(true, $result);
                        $this->addLeftJoin([$attribute, $alias], $result);
                    }


                    break;

                case 'isLinked':
                    if (!$result) {
                        break;
                    }
                    $alias = $attribute . 'IsLinkedFilter' . strval(rand(10000, 99999));
                    $part[$alias . '.id!='] = null;
                    $this->setDistinct(true, $result);
                    $this->addLeftJoin([$attribute, $alias], $result);
                    break;

                case 'linkedWith':
                    $seed = $this->getSeed();
                    $link = $attribute;
                    if (!$seed->hasRelation($link)) {
                        break;
                    }

                    $alias = $link . 'Filter' . strval(rand(10000, 99999));

                    if (is_null($value) || !$value && !is_array($value)) {
                        break;
                    }

                    $relationType = $seed->getRelationType($link);

                    if ($relationType == 'manyMany') {
                        $this->addLeftJoin([$link, $alias], $result);
                        $midKeys = $seed->getRelationParam($link, 'midKeys');

                        if (!empty($midKeys)) {
                            $key = $midKeys[1];
                            $part[$alias . 'Middle.' . $key] = $value;
                        }
                    } else {
                        if ($relationType == 'hasMany') {
                            $this->addLeftJoin([$link, $alias], $result);

                            $part[$alias . '.id'] = $value;
                        } else {
                            if ($relationType == 'belongsTo') {
                                $key = $seed->getRelationParam($link, 'key');
                                if (!empty($key)) {
                                    $part[$key] = $value;
                                }
                            } else {
                                if ($relationType == 'hasOne') {
                                    $this->addLeftJoin([$link, $alias], $result);
                                    $part[$alias . '.id'] = $value;
                                } else {
                                    break;
                                }
                            }
                        }
                    }
                    $this->setDistinct(true, $result);
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

                    $alias = $link . 'NotLinkedFilter' . strval(rand(10000, 99999));

                    if ($relationType == 'manyMany') {
                        $this->addLeftJoin([$link, $alias], $result);
                        $midKeys = $seed->getRelationParam($link, 'midKeys');

                        if (!empty($midKeys)) {
                            $key = $midKeys[1];
                            $result['joinConditions'][$alias] = [$key => $value];
                            $part[$alias . 'Middle.' . $key] = null;
                        }
                    } else {
                        if ($relationType == 'hasMany') {
                            $this->addLeftJoin([$link, $alias], $result);
                            $result['joinConditions'][$alias] = ['id' => $value];
                            $part[$alias . '.id'] = null;
                        } else {
                            if ($relationType == 'belongsTo') {
                                $key = $seed->getRelationParam($link, 'key');
                                if (!empty($key)) {
                                    $part[$key . '!='] = $value;
                                }
                            } else {
                                if ($relationType == 'hasOne') {
                                    $this->addLeftJoin([$link, $alias], $result);
                                    $part[$alias . '.id!='] = $value;
                                } else {
                                    break;
                                }
                            }
                        }
                    }
                    $this->setDistinct(true, $result);
                    break;

                case 'arrayAnyOf':
                    if (empty($value) || !is_array($value)) {
                        break;
                    }
                    $value = $this->prepareValueOptions($value, $attribute);
                    foreach ($value as $v) {
                        $part['OR'][] = [$attribute . '*' => '%"' . $v . '"%'];
                    }
                    break;
                case 'arrayNoneOf':
                    if (empty($value) || !is_array($value)) {
                        break;
                    }
                    $value = $this->prepareValueOptions($value, $attribute);

                    $andRows['AND'] = [];
                    foreach ($value as $v) {
                        $andRows['AND'][] = [$attribute . '!*' => '%"' . $v . '"%'];
                    }

                    $part['OR'] = [
                        [$attribute => null],
                        [$attribute => '[]'],
                        [$attribute => ''],
                        $andRows
                    ];

                    break;
                case 'arrayIsEmpty':
                    $part['OR'] = [
                        [$attribute => null],
                        [$attribute => '[]'],
                        [$attribute => '']
                    ];
                    break;
                case 'arrayIsNotEmpty':
                    $part['AND'] = [
                        [$attribute . '!=' => null],
                        [$attribute . '!=' => '[]'],
                        [$attribute . '!=' => '']
                    ];
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
            }
        }

        return $part;
    }

    public function applyOrder($sortBy, $desc, &$result)
    {
        $this->prepareResult($result);
        $this->order($sortBy, $desc, $result);
    }

    public function applyLimit($offset, $maxSize, &$result)
    {
        $this->prepareResult($result);
        $this->limit($offset, $maxSize, $result);
    }

    public function applyPrimaryFilter($filterName, &$result)
    {
        $this->prepareResult($result);

        $method = 'filter' . ucfirst($filterName);
        if (method_exists($this, $method)) {
            $this->$method($result);
        } else {
            $className = $this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'filters', $filterName, 'className']);
            if ($className) {
                if (!class_exists($className)) {
                    $GLOBALS['log']->error("Could find class for filter {$filterName}.");
                    return;
                }
                $impl = $this->getInjectableFactory()->createByClassName($className);
                if (!$impl) {
                    $GLOBALS['log']->error("Could not create filter {$filterName} implementation.");
                    return;
                }
                $impl->applyFilter($this->entityType, $filterName, $result, $this);
            }
        }
    }

    protected function prepareValueOptions($value, $field)
    {
        if (!is_array($value) || !is_string($field)) {
            return $value;
        }

        if (!empty($fieldDefs['type']) && $fieldDefs['type'] === 'array') {
            // escape slashes to search in escaped json
            $value = str_replace('\\', '\\\\\\\\', $value);
            $value = str_replace("/", "\\\\/", $value);
        }

        return $value;
    }

    public function applyFilter($filterName, &$result)
    {
        $this->applyPrimaryFilter($filterName, $result);
    }

    public function applyBoolFilter($filterName, &$result)
    {
        $this->prepareResult($result);

        $method = 'boolFilter' . ucfirst($filterName);
        if (method_exists($this, $method)) {
            $this->$method($result);
        }
    }

    public function applyTextFilter($textFilter, &$result)
    {
        $this->prepareResult($result);
        $this->textFilter($textFilter, $result);
    }

    /**
     * @param array $result
     * @param array $params
     */
    public function applyAdditional(array &$result, array $params)
    {
    }

    public function hasJoin($join, &$result)
    {
        if (in_array($join, $result['joins'])) {
            return true;
        }

        foreach ($result['joins'] as $item) {
            if (is_array($item) && count($item) > 1) {
                if ($item[1] == $join) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasLeftJoin($leftJoin, &$result)
    {
        if (in_array($leftJoin, $result['leftJoins'])) {
            return true;
        }

        foreach ($result['leftJoins'] as $item) {
            if (is_array($item) && count($item) > 1) {
                if ($item[1] == $leftJoin) {
                    return true;
                }
            }
        }

        return false;
    }

    public function addJoin($join, &$result)
    {
        if (empty($result['joins'])) {
            $result['joins'] = [];
        }

        $alias = $join;
        if (is_array($join)) {
            if (count($join) > 1) {
                $alias = $join[1];
            } else {
                $alias = $join[0];
            }
        }
        foreach ($result['joins'] as $j) {
            $a = $j;
            if (is_array($j)) {
                if (count($j) > 1) {
                    $a = $j[1];
                } else {
                    $a = $j[0];
                }
            }
            if ($a === $alias) {
                return;
            }
        }

        $result['joins'][] = $join;
    }

    public function addLeftJoin($leftJoin, &$result)
    {
        if (empty($result['leftJoins'])) {
            $result['leftJoins'] = [];
        }

        $alias = $leftJoin;
        if (is_array($leftJoin)) {
            if (count($leftJoin) > 1) {
                $alias = $leftJoin[1];
            } else {
                $alias = $leftJoin[0];
            }
        }
        foreach ($result['leftJoins'] as $j) {
            $a = $j;
            if (is_array($j)) {
                if (count($j) > 1) {
                    $a = $j[1];
                } else {
                    $a = $j[0];
                }
            }
            if ($a === $alias) {
                return;
            }
        }

        $result['leftJoins'][] = $leftJoin;
    }

    public function setJoinCondition($join, $condition, &$result)
    {
        $result['joinConditions'][$join] = $condition;
    }

    public function setDistinct($distinct, &$result)
    {
        $result['distinct'] = (bool)$distinct;
    }

    public function addAndWhere($whereClause, &$result)
    {
        $result['whereClause'][] = $whereClause;
    }

    public function addOrWhere($whereClause, &$result)
    {
        $result['whereClause'][] = array(
            'OR' => $whereClause
        );
    }

    protected function isValidNumber(string $value, string $thousandSeparator = ',', string $decimalSeparator = '.'): bool
    {
        $escapedThousandSeparator = preg_quote($thousandSeparator, '/');
        $escapedDecimalSeparator = preg_quote($decimalSeparator, '/');

        $pattern = '/^';
        $pattern .= '-?';
        $pattern .= '\d+';
        $pattern .= '(?:' . $escapedThousandSeparator . '\d{3})*';
        $pattern .= '(?:' . $escapedDecimalSeparator . '\d+)?';
        $pattern .= '$/';

        return (bool)preg_match($pattern, $value);
    }

    protected function textFilter($textFilter, &$result)
    {
        $autocompletion = false;
        if (mb_strpos($textFilter, 'AUTOCOMPLETE:') === 0) {
            $textFilter = mb_substr($textFilter, strlen('AUTOCOMPLETE:'));
            $autocompletion = true;
        }
        $fieldDefs = $this->getSeed()->getAttributes();
        $fieldList = $this->getTextFilterFieldList();
        $group = [];

        $textFilterContainsMinLength = $this->getConfig()->get('textFilterContainsMinLength', self::MIN_LENGTH_FOR_CONTENT_SEARCH);

        if (mb_strpos($textFilter, 'ft:') === 0) {
            $textFilter = mb_substr($textFilter, 3);
        }

        $skipWidlcards = false;

        if (mb_strpos($textFilter, '*') !== false) {
            $skipWidlcards = true;
            $textFilter = str_replace('*', '%', $textFilter);
        }

        foreach ($fieldList as $field) {
            $attributeType = null;
            if (!empty($fieldDefs[$field]['type'])) {
                $attributeType = $fieldDefs[$field]['type'];
            }

            if (in_array($attributeType, ['int', 'float'])) {
                $thousandSeparator = $this->getConfig()->get('thousandSeparator', ',');
                $decimalMark = $this->getConfig()->get('decimalMark', '.');
                if ($this->getUser() && !empty($locale = $this->getUser()->getLocale())) {
                    $thousandSeparator = $locale->get('thousandSeparator') ?? $thousandSeparator;
                    $decimalMark = $locale->get('decimalMark') ?? $decimalMark;
                }

                if ($this->isValidNumber($textFilter, $thousandSeparator, $decimalMark)) {
                    $value = str_replace($thousandSeparator, '', $textFilter);
                    $value = str_replace($decimalMark, '.', $value);
                    $value = $attributeType === 'int' ? intval($value) : floatval($value);
                    //avoid the range limit for INTEGER in database
                    if ($attributeType === 'int' && abs($value) > (2 ^ 31)) {
                        continue;
                    }
                    $group[$field] = $value;

                }
                continue;
            }

            if (Converter::isPgSQL($this->getEntityManager()->getConnection()) &&
                in_array($attributeType, ['bool', 'date', 'datetime'])) {
                $field = "VARCHAR:$field";
            }

            if (!$skipWidlcards) {
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
                    $ta = $mapper->getQueryConverter()->getMainTableAlias();
                    $parameter = 'name_' . Util::generateUniqueHash();
                    $alias = 'match_priority_' . Util::generateUniqueHash();
                    $qb->addSelect("CASE WHEN LOWER($ta.name) LIKE LOWER(:$parameter) THEN 1 ELSE 2 END AS $alias");
                    $qb->orderBy("$alias");
                    $qb->addOrderBy("$ta.name");
                    $qb->setParameter($parameter, $expression);
                };
            }
        }

        if (count($group) === 0) {
            $result['whereClause'][] = [
                'id' => null
            ];
        }

        $result['whereClause'][] = [
            'OR' => $group
        ];
    }

    public function applyAccess(&$result)
    {
        $this->prepareResult($result);
        $this->access($result);
    }

    protected function boolFilters($params, &$result)
    {
        if (!empty($params['boolFilterList']) && is_array($params['boolFilterList'])) {
            foreach ($params['boolFilterList'] as $filterName) {
                $this->applyBoolFilter($filterName, $result);
            }
        }
    }

    protected function getBoolFilterWhere($filterName)
    {
        $method = 'getBoolFilterWhere' . ucfirst($filterName);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
    }

    /**
     * @param array $result
     *
     * @return void
     */
    protected function boolFilterOnlyMy(&$result)
    {
        $where = [];

        if ($this->hasOwnerUserField()) {
            $where[] = ['ownerUserId' => $this->getUser()->id];
        }

        if ($this->hasAssignedUserField()) {
            $where[] = ['assignedUserId' => $this->getUser()->id];
        }

        if (!$this->hasOwnerUserField() && !$this->hasAssignedUserField()) {
            $where[] = ['createdById' => $this->getUser()->id];
        }

        $result['whereClause'][] = [
            'OR' => $where
        ];
    }

    protected function boolFilterOnlyMyTeams(&$result): void
    {
        if (!$this->hasTeamsField()) {
            return;
        }

        $result['callbacks'][] = function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) {
            $ta = $mapper->getQueryConverter()->getMainTableAlias();
            $sql = "($ta.id IN (SELECT entity_id FROM entity_team WHERE deleted=:false AND entity_type=:entityType AND team_id IN (:teamsIds)))";

            $qb->andWhere($sql)
                ->setParameter('teamsIds', $this->getUser()->getLinkMultipleIdList('teams'), Connection::PARAM_STR_ARRAY)
                ->setParameter('entityType', $this->entityType)
                ->setParameter('false', false, ParameterType::BOOLEAN);
        };
    }

    /**
     * @param array $result
     *
     * @return void
     */
    protected function boolFilterOwnedByMe(&$result)
    {
        if ($this->hasOwnerUserField()) {
            $result['whereClause'][] = [
                'ownerUserId' => $this->getUser()->id
            ];
        } else {
            $result['whereClause'][] = [
                'createdById' => $this->getUser()->id
            ];
        }
    }

    /**
     * @param array $result
     *
     * @return void
     */
    protected function boolFilterAssignedToMe(&$result)
    {
        if ($this->hasAssignedUserField()) {
            $result['whereClause'][] = [
                'assignedUserId' => $this->getUser()->id
            ];
        } else {
            $result['whereClause'][] = [
                'createdById' => $this->getUser()->id
            ];
        }
    }

    /**
     * @return RDB
     */
    protected function getRepository()
    {
        return $this
            ->getEntityManager()
            ->getRepository($this->entityType);
    }

    protected function boolFilterNotParents(array &$result)
    {
        $id = $this->getBoolFilterParameter('notParents');

        $ids = array_merge([$id], $this->getRepository()->getParentsRecursivelyArray($id));
        $ids = array_merge($ids, array_column($this->getRepository()->get($id)->get('children')->toArray(), 'id'));

        $result['whereClause'][] = ['id!=' => $ids];
    }

    protected function boolFilterNotChildren(array &$result)
    {
        $id = $this->getBoolFilterParameter('notChildren');

        $ids = array_merge([$id], $this->getRepository()->getChildrenRecursivelyArray($id));
        $ids = array_merge($ids, array_column($this->getRepository()->get($id)->get('parents')->toArray(), 'id'));

        $result['whereClause'][] = ['id!=' => $ids];
    }

    protected function boolFilterOnlyFollowed(array &$result)
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

    protected function boolFilterNotEntity(&$result)
    {
        if (!empty($value = $this->getBoolFilterParameter('notEntity'))) {
            if (is_string($value)) {
                $ids = [$value];
            } else {
                $ids = (array)$value;
            }
            foreach ($ids as $id) {
                $result['whereClause'][] = [
                    'id!=' => $id
                ];
            }
        }
    }

    /**
     * NotAssociatedRecord filter
     *
     * @param array $result
     */
    protected function boolFilterNotAssociatedRecords(&$result)
    {
        // prepare data
        $data = (array)$this->getBoolFilterParameter('notAssociatedRecords');

        if (!empty($data['associationId']) && !empty($data['mainRecordId'])) {
            foreach ($this->getAssociatedRecords($data['associationId'], $data['mainRecordId']) as $id) {
                $result['whereClause'][] = [
                    'id!=' => $id
                ];
            }
        }
    }

    /**
     * Get associated records
     *
     * @param string $associationId
     * @param string $recordId
     *
     * @return array
     */
    protected function getAssociatedRecords(string $associationId, string $mainRecordId): array
    {
        $scope = $this->entityType;

        return $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select("associated_item_id")
            ->from(Util::toUnderScore("Associated$scope"))
            ->where('associating_item_id = :mainRecordId')
            ->andWhere('association_id = :associationId')
            ->andWhere('deleted = :false')
            ->setParameter('associationId', $associationId, Mapper::getParameterType($associationId))
            ->setParameter('mainRecordId', $mainRecordId, Mapper::getParameterType($mainRecordId))
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchFirstColumn();
    }

    /**
     * @param string $filterName
     *
     * @return mixed
     */
    protected function getBoolFilterParameter(string $filterName)
    {
        if (isset($this->selectParameters['where'])) {
            foreach ($this->selectParameters['where'] as $key => $row) {
                if ($row['type'] == 'bool' && !empty($row['data'][$filterName])) {
                    return $row['data'][$filterName];
                }
            }
        }

        return null;
    }

    public function setSelectManagerFactory(SelectManagerFactory $selectManagerFactory): Base
    {
        $this->selectManagerFactory = $selectManagerFactory;

        return $this;
    }

    protected function getSelectManagerFactory(): SelectManagerFactory
    {
        return $this->selectManagerFactory;
    }

    protected function createSelectManager(string $scope): Base
    {
        $selectManager = $this->getSelectManagerFactory()->create($scope);
        $selectManager->isSubQuery = true;

        return $selectManager;
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    protected function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    protected function getUser(): User
    {
        return $this->user;
    }

    protected function getAcl(): Acl
    {
        return $this->acl;
    }

    protected function getConfig(): Config
    {
        return $this->config;
    }

    protected function getAclManager(): AclManager
    {
        return $this->aclManager;
    }

    protected function getInjectableFactory(): InjectableFactory
    {
        return $this->injectableFactory;
    }

    public function setEntityType(string $entityType): void
    {
        $this->entityType = $entityType;
    }

    protected function getEntityType(): string
    {
        return $this->entityType;
    }
}
