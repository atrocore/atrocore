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
use Atro\Core\Utils\Util;
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

        // set bool attribute with notNull true to false if they are null
        $this->setNotNullBoolAttributeToFalse();

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE master_data_entity ADD delete_invalid_masters_automatically BOOLEAN DEFAULT 'false' NOT NULL");
        } else {
            $this->exec("ALTER TABLE master_data_entity ADD delete_invalid_masters_automatically TINYINT(1) DEFAULT '0' NOT NULL");
        }
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


    public function setNotNullBoolAttributeToFalse(): void
    {
        $dir = 'data/metadata/scopes';

        $entities = ['Product'];

        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (!in_array($item, ['.', '..'])) {
                    $parts = explode('.', $item);
                    $scope = $parts[0];

                    $content = @json_decode(file_get_contents($dir . '/' . $item), true);
                    if (empty($content)) {
                        continue;
                    }

                    if (!empty($content['hasAttribute'])) {
                        $entities[] = $scope;
                        continue;
                    }

                    if (!empty($content['primaryEntityId']) && in_array($content['primaryEntityId'], $entities)) {
                        $entities[] = $scope;
                    }
                }
            }
        }

        $subQb = $this->getDbal()->createQueryBuilder()->select('a.id')
            ->from('attribute', 'a')
            ->where('a.type = :bool')
            ->andWhere('a.not_null = :true');

        foreach ($entities  as $scope) {
            $pavTable = Util::toUnderScore(lcfirst($scope)) . "_attribute_value";

            if(!$this->getCurrentSchema()->hasTable($pavTable)) {
                continue;
            }

            $qb = $this->getDbal()->createQueryBuilder();

            $qb->update($pavTable, 'pav')
                ->set('bool_value', ':false')
                ->where($qb->expr()->in('pav.attribute_id', $subQb->getSQL()))
                ->andWhere('pav.bool_value is NULL AND pav.deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('bool', 'bool')
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeStatement();
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
