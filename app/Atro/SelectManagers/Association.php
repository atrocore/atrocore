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

use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\SelectManagers\Base;

/**
 * Class of Association
 */
class Association extends Base
{
    /**
     * Get associated record associations
     *
     * @param string $mainRecordId
     * @param string $relatedRecordId
     *
     * @return array
     */
    public function getAssociatedRecordAssociations(string $scope, string $mainRecordId, ?string $relatedRecordId = null): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $qb = $connection->createQueryBuilder()
            ->select('distinct(association_id)')
            ->from(Util::toUnderScore("Associated$scope"), 'a1')
            ->where('associating_item_id = :mainRecordId')
            ->innerJoin('a1', Util::toUnderScore($scope), 't1', "t1.id = a1.associated_item_id and t1.deleted = :false")
            ->andWhere('a1.deleted = :false')
            ->setParameter('mainRecordId', $mainRecordId, Mapper::getParameterType($mainRecordId))
            ->setParameter('false', false, Mapper::getParameterType(false));

        if (!empty($relatedRecordId)) {
            $qb->andWhere('associated_item_id = :relatedRecordId');
            $qb->setParameter('relatedRecordId', $relatedRecordId, Mapper::getParameterType($relatedRecordId));
        }

        return $qb->fetchFirstColumn();
    }

    public function getRelatedRecordAssociations(string $scope, string $relatedRecordId): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $qb = $connection->createQueryBuilder()
            ->select('distinct(association_id)')
            ->from(Util::toUnderScore("Associated$scope"), 'a1')
            ->where( 'associated_item_id = :relatedRecordId')
            ->innerJoin('a1', Util::toUnderScore($scope), 't1', "t1.id = a1.associating_item_id and t1.deleted = :false")
            ->andWhere('a1.deleted = :false')
            ->setParameter('relatedRecordId', $relatedRecordId, Mapper::getParameterType($relatedRecordId))
            ->setParameter('false', false, Mapper::getParameterType(false));

        return $qb->fetchFirstColumn();
    }


    /**
     * NotUsedAssociations filter
     *
     * @param array $result
     */
    protected function boolFilterNotUsedAssociations(&$result): void
    {
        // prepare data
        $data = (array)$this->getBoolFilterParameter('notUsedAssociations');

        if (!empty($data['relatedProductId'])) {
            $associationIds = $this
                ->getAssociatedRecordAssociations($data['scope'], $data['mainRecordId'], $data['relatedRecordId']);
            foreach ($associationIds as $id) {
                $result['whereClause'][] = [
                    'id!=' => $id
                ];
            }
        }
    }

    protected function boolFilterUsedAssociations(&$result): void
    {
        // prepare data
        $data = (array)$this->getBoolFilterParameter('usedAssociations');

        if (!empty($data['mainRecordId'])) {
            $associationIds = $this
                ->getAssociatedRecordAssociations($data['scope'], $data['mainRecordId']);
            $result['whereClause'][] = [
                'id' => $associationIds
            ];
        }
    }

    protected function boolFilterRelatedAssociations(&$result): void
    {
        // prepare data
        $data = (array)$this->getBoolFilterParameter('relatedAssociations');

        if (!empty($data['relatedRecordId'])) {
            $associationIds = $this
                ->getRelatedRecordAssociations($data['scope'], $data['relatedRecordId']);
            $result['whereClause'][] = [
                'id' => $associationIds
            ];
        }
    }

    protected function boolFilterOnlyForEntity(array &$result): void
    {
        $entityName = (string)$this->getBoolFilterParameter('onlyForEntity');
        if (!empty($entityName)) {
            $result['whereClause'][] = [
                'entityId' => $entityName
            ];
        }
    }
}
