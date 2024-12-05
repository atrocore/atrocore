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

namespace Atro\Services;

use Atro\Core\Templates\Services\Base;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Espo\ORM\EntityCollection;

class Bookmark extends Base
{
    public function findEntities($params)
    {
        $params['where'][] = [
            "attribute" => "ownerUserId",
            "type" => "equals",
            "value" => $this->getUser()->id
        ];
        $params['sortBy'] = "entityType";

        $result = parent::findEntities($params);
        $groupedCollections = [];

        foreach ($result['collection'] as $item) {
            $groupedCollections[$item->get('entityType')][$item->get('entityId')] = $item->toArray();
        }

        foreach ($groupedCollections as $entityType => $items) {
            /** @var Connection $connection */
            $connection = $this->getEntityManager()->getConnection();
            $entityNames = $connection->createQueryBuilder()
                ->select('id, name, deleted')
                ->from($connection->quoteIdentifier(strtolower(Util::toCamelCase($entityType))))
                ->where('id IN (:ids)')
                ->setParameter('ids', array_column(array_values($items), 'entityId'), Connection::PARAM_STR_ARRAY)
                ->fetchAllAssociative();

            $entityNameByIds = [];
            foreach ($entityNames as $entityName) {
                $entityNameByIds[$entityName['id']] = $entityName;
            }

            foreach ($items as $entityId => $item) {
                if(!empty($entityNameByIds[$entityId])) {
                    $data = $entityNameByIds[$entityId];
                    if(!empty($data['deleted'])){
                        $this->getEntityManager()->removeEntity(
                            $this->getEntityManager()->getRepository('Bookmark')->get($item['id'])
                        );
                        unset($groupedCollections[$entityType][$entityId]);
                        continue;
                    }
                    $groupedCollections[$entityType][$entityId]['entityName'] = $data['name'];
                }else{

                    $this->getEntityManager()->removeEntity(
                        $this->getEntityManager()->getRepository('Bookmark')->get($item['id'])
                    );
                    unset($groupedCollections[$entityType][$entityId]);
                }
            }

            $groupedCollections[$entityType] = array_values($groupedCollections[$entityType]);
        }

        return [
            "total" => $result['total'],
            "list" => $groupedCollections
        ];
    }
}
