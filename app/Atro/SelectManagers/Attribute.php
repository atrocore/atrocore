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

namespace Atro\SelectManagers;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;
use Atro\Core\SelectManagers\Base;

class Attribute extends Base
{
    public function filterByType(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
        $attributeTypes = array_keys($this->getMetadata()->get('attributes'));

        $qb->andWhere("{$tableAlias}.type IN (:attributeTypes)");
        $qb->setParameter('attributeTypes', $attributeTypes, Mapper::getParameterType($attributeTypes));
    }

    public function applyAdditional(array &$result, array $params): void
    {
        parent::applyAdditional($result, $params);

        $result['callbacks'][] = [$this, 'filterByType'];
    }

    protected function boolFilterOnlyMultilang(array &$result): void
    {
        $result['callbacks'][] = [$this, 'filterOnlyMultilang'];
    }

    /**
     * The multilingual flag is a setting of the attribute type, it is stored in the table of the subtype.
     */
    public function filterOnlyMultilang(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $queryConverter = $mapper->getQueryConverter();
        $tableAlias = $queryConverter->getMainTableAlias();

        // the table of a subtype is read only if there are attributes of its type, and only once for all of them
        $conditions = [];
        foreach ($this->getEntityManager()->getContainer()->get(AttributeFieldConverter::class)->getSubtypeColumns() as $type => $subtype) {
            if (in_array('is_multilang', $subtype['columns'])) {
                $conditions[] = "({$tableAlias}.type = :multilangType_{$type} AND {$tableAlias}.id IN (SELECT supertype_id FROM " . $queryConverter->quoteIdentifier($subtype['table']) . ' WHERE is_multilang = :onlyMultilang))';
                $qb->setParameter("multilangType_{$type}", $type);
            }
        }

        $qb->andWhere(empty($conditions) ? '1 = 0' : '(' . implode(' OR ', $conditions) . ')');
        $qb->setParameter('onlyMultilang', true, ParameterType::BOOLEAN);
    }

    protected function boolFilterNotForbiddenForEditFields(array &$result): void
    {
        if ($this->getUser()->isAdmin()) {
            return;
        }

        $entityName = $this->getBoolFilterParameter('notForbiddenForEditFields')['entityName'] ?? null;
        $entityId = $this->getBoolFilterParameter('notForbiddenForEditFields')['entityId'] ?? null;

        if (empty($entityName) || empty($entityId)) {
            return;
        }

        $forbiddenFieldsList = $this->getAcl()->getScopeForbiddenAttributeList($entityName, 'edit');

        foreach ($this->getUser()->get('roles') ?? [] as $role) {
            $aclData = $this->getEntityManager()->getRepository('Role')->getAclData($role);
            foreach ($aclData->attributes->$entityName ?? [] as $field => $attributeId) {
                if (in_array($field, $forbiddenFieldsList)) {
                    $result['whereClause'][] = [
                        'id!=' => $attributeId
                    ];
                }
            }
        }
    }

    protected function boolFilterNotLinkedWithCurrent(array &$result): void
    {
        $attributeId = (string)$this->getBoolFilterParameter('notLinkedWithCurrent');

        if(empty($attributeId)) {
            return;
        }

        $result['whereClause'][] = [
            'OR' => [
                'compositeAttributeId!=' => $attributeId,
                'compositeAttributeId=' => null
            ]
        ];
    }

    protected function boolFilterOnlyAttributeTypes(array &$result): void
    {
        $types = $this->getBoolFilterParameter('onlyAttributeTypes');

        if (!empty($types)) {
            $result['whereClause'][] = [
                'type=' => $types
            ];
        }
    }

    protected function boolFilterOnlyCompositeAttributes(array &$result): void
    {
        $attributeId = (string)$this->getBoolFilterParameter('onlyCompositeAttributes');

        $result['whereClause'][] = [
            'type=' => 'composite'
        ];

        if(!empty($attributeId)) {
            $result['whereClause'][] = [
                'id!=' => $attributeId,
            ];
        }
    }

    protected function boolFilterOnlyEditableAttributes(array &$result): void
    {
        $result['whereClause'][] = [
            'type!=' => ['composite', 'script']
        ];
    }

    protected function boolFilterNotCompositeType(array &$result): void
    {
        $result['whereClause'][] = ['type!=' => 'composite'];
    }

    protected function boolFilterNotParentCompositeAttribute(array &$result): void
    {
        $attributeId = (string)$this->getBoolFilterParameter('notParentCompositeAttribute');
        if (empty($attributeId)) {
            return;
        }

        $ids = [$attributeId];
        $this->getEntityManager()->getRepository('Attribute')->prepareAllParentsCompositeAttributesIds($attributeId, $ids);

        $result['whereClause'][] = [
            'id!=' => $ids
        ];
    }

    protected function boolFilterNotChildCompositeAttribute(array &$result): void
    {
        $attributeId = (string)$this->getBoolFilterParameter('notChildCompositeAttribute');
        if (empty($attributeId)) {
            return;
        }

        $ids = [$attributeId];
        $this->getEntityManager()->getRepository('Attribute')->prepareAllChildrenCompositeAttributesIds($attributeId, $ids);

        $result['whereClause'][] = [
            'id!=' => $ids
        ];
    }

    protected function boolFilterOnlyForEntity(array &$result): void
    {
        $entityName = (string)$this->getBoolFilterParameter('onlyForEntity');
        if (!empty($entityName)) {
            if ($this->getMetadata()->get("scopes.$entityName.primaryEntityId")) {
                $entityName = $this->getMetadata()->get("scopes.$entityName.primaryEntityId");
            }
            $result['whereClause'][] = [
                'entityId' => $entityName
            ];
        }
    }

    protected function boolFilterOnlyForAttributePanel(array &$result): void
    {
        $attributePanelId = (string)$this->getBoolFilterParameter('onlyForAttributePanel');
        if (!empty($attributePanelId)) {
            $result['whereClause'][] = [
                'attributePanelId' => $attributePanelId
            ];
        }
    }

    protected function boolFilterFromAttributesTab(array &$result): void
    {
        $data = (array)$this->getBoolFilterParameter('fromAttributesTab');

        if (isset($data['tabId'])) {
            $result['whereClause'][] = [
                'attributeTabId=' => empty($data['tabId']) ? null : $data['tabId']
            ];
        }
    }

    protected function boolFilterNotLinkedWithAnyRecord(array &$result): void
    {
        $result['callbacks'][] = function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) {
            $alias = $mapper->getQueryConverter()->getMainTableAlias();

            $entityTypes = [];
            foreach ($this->getMetadata()->get('scopes', []) as $scope => $scopeDefs) {
                if (!empty($scopeDefs['hasAttribute'])) {
                    $entityTypes[] = $scope;
                }
            }

            if (empty($entityTypes)) {
                return;
            }

            $conditions = [];
            foreach ($entityTypes as $entityType) {
                $avTable = Util::toUnderScore(lcfirst($entityType)) . '_attribute_value';
                $conditions[] = "({$alias}.entity_id = '{$entityType}' AND NOT EXISTS ("
                    . "SELECT 1 FROM {$avTable} WHERE attribute_id = {$alias}.id"
                    . "))";
            }

            $qb->andWhere('(' . implode(' OR ', $conditions) . ')');
        };
    }
}
