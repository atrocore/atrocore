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

use Doctrine\DBAL\ParameterType;
use Espo\Core\SelectManagers\Base;

class NotificationTemplate extends Base
{
    protected function boolFilterTransportType(array &$result)
    {
        if (!empty($type = $this->getBoolFilterParameter('transportType'))) {
            $connection = $this->getEntityManager()->getConnection();

            $list = $connection
                ->createQueryBuilder()
                ->select('nr.id, nr.data')
                ->from($connection->quoteIdentifier('notification_rule'), 'nr')
                ->where("nr.data LIKE :condition")
                ->andWhere("nr.deleted = :false")
                ->setParameter('condition', '%"' . $type . 'Active":true%')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            $ids = [];
            $field = $type . 'TemplateId';

            foreach ($list as $item) {
                $data = @json_decode($item['data'], true);

                if (!empty($data['field'][$field])) {
                    $ids[] = $data['field'][$field];
                }
            }

            $result['whereClause'][] = [
                'id' => array_unique($ids)
            ];
        }
    }
}
