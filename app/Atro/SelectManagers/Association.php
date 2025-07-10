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
    public function getAssociatedRecordAssociations(string $scope, string $mainRecordId, ?string $relatedRecordId = null) : array
    {
        $connection = $this->getEntityManager()->getConnection();


        $qb = $connection->createQueryBuilder()
            ->select('distinct(association_id)')
            ->from(Util::toUnderScore("Associated$scope"))
            ->where(Util::toUnderScore("main{$scope}Id") . ' = :mainRecordId')
            ->andWhere('deleted = :false')
            ->setParameter('mainRecordId', $mainRecordId, Mapper::getParameterType($mainRecordId))
            ->setParameter('false', false, Mapper::getParameterType(false));

        if (!empty($relatedProductId)) {
            $qb->andWhere(Util::toUnderScore("related{$scope}Id") . ' = :relatedRecordId');
            $qb->setParameter('relatedRecordId', $relatedRecordId, Mapper::getParameterType($relatedRecordId));
        }

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
