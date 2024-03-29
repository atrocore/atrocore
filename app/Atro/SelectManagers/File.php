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

namespace Atro\SelectManagers;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\SelectManagers\Base;
use Espo\ORM\IEntity;

class File extends Base
{
    protected function boolFilterLinkedWithFolder(array &$result): void
    {
        $result['callbacks'][] = [$this, 'filterLinkedWithFolder'];
    }

    public function filterLinkedWithFolder(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $folderId = (string)$this->getBoolFilterParameter('linkedWithFolder');
        if (empty($folderId)) {
            return;
        }

        $ids = $this->getEntityManager()->getRepository('Folder')->getChildrenRecursivelyArray($folderId);
        $ids = array_merge($ids, [$folderId]);

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $qb->andWhere("$tableAlias.folder_id IN (:foldersIds)");
        $qb->setParameter('foldersIds', $ids, Connection::PARAM_STR_ARRAY);
    }

    protected function boolFilterOnlyType(&$result)
    {
        $typeId = (string)$this->getBoolFilterParameter('onlyType');
        if (empty($typeId)) {
            return;
        }

        $result['whereClause'][] = [
            'typeId' => $typeId
        ];
    }
}
