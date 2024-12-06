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
    function findEntities($params)
    {
        $params['where'][] = [
            "attribute" => "userId",
            "type" => "equals",
            "value" => $this->getUser()->id
        ];

        $params['sortBy'] = "entityType";

        $result = parent::findEntities($params);
        $collection = $result['collection'];
        $count = $result['total'];

        $groupedCollections = [];

        foreach ($collection as $key => $item) {
            $item->_key = $key;
            $groupedCollections[$item->get('entityType')][$item->get('entityId')] = $item;
        }

        $result = [];

        foreach ($groupedCollections as $entityType => $items) {
            /** @var Connection $connection */
            $connection = $this->getEntityManager()->getConnection();
            $entityNames = $connection->createQueryBuilder()
                ->select('id, name, deleted')
                ->from($connection->quoteIdentifier(strtolower(Util::toCamelCase($entityType))))
                ->where('id IN (:ids)')
                ->setParameter('ids', array_keys($items), Connection::PARAM_STR_ARRAY)
                ->fetchAllAssociative();

            $entityNameByIds = [];
            foreach ($entityNames as $entityName) {
                $entityNameByIds[$entityName['id']] = $entityName;
            }

            foreach ($items as $entityId => $item) {
                if (!empty($entityNameByIds[$entityId])) {
                    $data = $entityNameByIds[$entityId];
                    if (!empty($data['deleted'])) {
                        $this->getEntityManager()->removeEntity($item);
                        unset($connection[$item->_key]);
                        unset($groupedCollections[$item->get('entityType')][$item->get('entityId')]);
                        $count--;
                        continue;
                    }
                    $item->set('entityName', $data['name']);
                } else {
                    $this->getEntityManager()->removeEntity($item);
                    unset($connection[$item->_key]);
                    unset($groupedCollections[$item->get('entityType')][$item->get('entityId')]);
                    $count--;
                }
            }
            $collectionArr = array_map(fn($item) => $item->toArray(), array_values($items));
            $result[$entityType] = [
                "collection" => $collectionArr,
                "key" => $entityType,
                "rowList" => array_column($collectionArr, 'id')
            ];
        }

        return [
            "total" => $count,
            "list" => array_values($result)
        ];
    }
}
