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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Relation;
use Doctrine\DBAL\ParameterType;

class EntityTeam extends Relation
{
    public function hasDeletedRecordsToClear(): bool
    {
        return true;
    }

    public function clearDeletedRecords(): void
    {
        foreach ($this->getMetadata()->get('entityDefs', []) as $scope => $data) {
            $tableName = $this->getEntityManager()->getMapper()->toDb($scope);
            try {
                $this->getConnection()->createQueryBuilder()
                    ->delete('entity_team')
                    ->where('entity_type=:entityType')
                    ->andWhere('entity_id NOT IN (SELECT id FROM ' . $this->getConnection()->quoteIdentifier($tableName) . ' WHERE deleted=:false)')
                    ->setParameter('entityType', $scope)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }
}
