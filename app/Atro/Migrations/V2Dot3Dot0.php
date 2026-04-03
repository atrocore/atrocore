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

class V2Dot3Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-26 12:00:00');
    }

    public function up(): void
    {
        copy('vendor/atrocore/copy/public/apidocs/index.html', 'public/apidocs/index.html');

        $this->migrateExtensibleEnumOptionSortOrder();
    }

    public function migrateExtensibleEnumOptionSortOrder(): void
    {
        // migrate sorting from extensible_enum_extensible_enum_option to extensible_enum_option.sort_order
        // the subquery picks the first (MIN) extensible_enum_id per option so each option appears only once
        $subQuery = 'SELECT sub.extensible_enum_option_id, MIN(sub.extensible_enum_id) AS first_enum_id'
            . ' FROM extensible_enum_extensible_enum_option sub'
            . ' INNER JOIN extensible_enum e ON e.id = sub.extensible_enum_id AND e.deleted = false'
            . ' WHERE sub.deleted = false'
            . ' GROUP BY sub.extensible_enum_option_id';

        $offset = 0;
        $batchSize = 5000;

        while (true) {
            $rows = $this->getDbal()->createQueryBuilder()
                ->select('t.extensible_enum_option_id', 't.sorting')
                ->from('extensible_enum_extensible_enum_option', 't')
                ->innerJoin('t', '(' . $subQuery . ')', 'first', 't.extensible_enum_option_id = first.extensible_enum_option_id AND t.extensible_enum_id = first.first_enum_id')
                ->where('t.deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->fetchAllAssociative();

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $this->getDbal()->createQueryBuilder()
                    ->update('extensible_enum_option')
                    ->set('sort_order', ':sorting')
                    ->setParameter('sorting', $row['sorting'], ParameterType::INTEGER)
                    ->where('id = :id')
                    ->setParameter('id', $row['extensible_enum_option_id'])
                    ->executeStatement();
            }

            $offset += $batchSize;
        }
    }
}
