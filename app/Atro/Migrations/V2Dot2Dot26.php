<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V2Dot2Dot26 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-12 10:00:00');
    }

    public function up(): void
    {
        $rows = $this->getDbal()->createQueryBuilder()
            ->select('id', 'data')
            ->from($this->getDbal()->quoteIdentifier('attribute'))
            ->where('type IN (:types)')
            ->andWhere('data IS NOT NULL')
            ->setParameter('types', ['link', 'linkMultiple'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $data = @json_decode($row['data'], true);

            if (!is_array($data)) {
                continue;
            }

            if (($data['field']['entityType'] ?? null) !== 'ExtensibleEnumOption') {
                continue;
            }

            $data['field']['entityField'] = 'name';

            $this->getDbal()->createQueryBuilder()
                ->update('attribute')
                ->set('data', ':data')
                ->where('id = :id')
                ->setParameter('data', json_encode($data))
                ->setParameter('id', $row['id'])
                ->executeStatement();
        }
    }
}