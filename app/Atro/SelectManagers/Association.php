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
     * Get associated products associations
     *
     * @param string $mainProductId
     * @param string $relatedProductId
     *
     * @return array
     */
    public function getAssociatedProductAssociations($scope, $mainRecordId, $relatedRecordId = null)
    {
        $connection = $this->getEntityManager()->getConnection();


        $qb = $connection->createQueryBuilder()
            ->select('association_id')
            ->from(Util::toUnderScore("Associated$scope"))
            ->where(Util::toUnderScore("main{$scope}Id") . ' = :mainRecordId')
            ->andWhere('deleted = :false')
            ->setParameter('mainRecordId', $mainRecordId, Mapper::getParameterType($mainRecordId))
            ->setParameter('false', false, Mapper::getParameterType(false));

        if (!empty($relatedProductId)) {
            $qb->andWhere(Util::toUnderScore("related{$scope}Id") . ' = :relatedRecordId');
            $qb->setParameter('relatedRecordId', $relatedRecordId, Mapper::getParameterType($relatedRecordId));
        }

        return $qb->fetchAllAssociative();
    }

    /**
     * NotUsedAssociations filter
     *
     * @param array $result
     */
    protected function boolFilterNotUsedAssociations(&$result)
    {
        // prepare data
        $data = (array)$this->getBoolFilterParameter('notUsedAssociations');

        if (!empty($data['relatedProductId'])) {
            $assiciations = $this
                ->getAssociatedProductAssociations($data['scope'], $data['mainProductId'], $data['relatedProductId']);
            foreach ($assiciations as $row) {
                $result['whereClause'][] = [
                    'id!=' => (string)$row['association_id']
                ];
            }
        }
    }

    protected function boolFilterUsedAssociations(&$result)
    {
        // prepare data
        $data = (array)$this->getBoolFilterParameter('usedAssociations');

        if (!empty($data['mainRecordId'])) {
            $associations = $this
                ->getAssociatedProductAssociations($data['scope'], $data['mainRecordId']);
            $result['whereClause'][] = [
                'id' => array_map(function ($item) {
                    return (string)$item['association_id'];
                }, $associations)
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
