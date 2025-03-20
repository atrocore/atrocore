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
use Atro\ORM\DB\RDB\Mapper;

class V1Dot13Dot38 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-03-20 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE saved_search (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', data TEXT DEFAULT NULL, user_id VARCHAR(255) DEFAULT NULL, entity_type VARCHAR(255) DEFAULT NULL, \"primary\" VARCHAR(255) DEFAULT NULL, is_public BOOLEAN DEFAULT 'false' NOT NULL, PRIMARY KEY(id));");
            $this->exec("COMMENT ON COLUMN saved_search.data IS '(DC2Type:jsonObject)'");
        } else {
            $this->exec("CREATE TABLE saved_search (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', user_id VARCHAR(255) DEFAULT NULL, entity_type VARCHAR(255) DEFAULT NULL, `primary` VARCHAR(255) DEFAULT NULL, is_public TINYINT(1) DEFAULT '0' NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }

        $results = $this->getConnection()->createQueryBuilder()
            ->select('id, preset_filters')
            ->from($this->getConnection()->quoteIdentifier('user'))
            ->fetchAllAssociative();

        foreach ($results as $result) {
            $presetFilters = @json_decode($result['preset_filters'], true);
            $userId = $result['id'];
            if (!empty($presetFilters)) {
                foreach ($presetFilters as $scope => $filters) {
                    foreach ($filters as $filter) {
                        if (empty($filter['id']) || empty($filter['label']) || empty($filter['data']) || !array_key_exists('primary', $filter)) {
                            continue;
                        }
                        $this->getConnection()->createQueryBuilder()
                            ->insert('saved_search')
                            ->setValue('id', ':id')
                            ->setValue('name', ':name')
                            ->setValue('entity_type', ':entityType')
                            ->setValue('user_id', ':userId')
                            ->setValue('data', ':data')
                            ->setValue($this->getConnection()->quoteIdentifier('primary'), ':primary')
                            ->setParameter('id', $filter['id'])
                            ->setParameter('name', $filter['label'])
                            ->setParameter('entityType', $scope)
                            ->setParameter('userId', $userId)
                            ->setParameter('data', json_encode($filter['data']))
                            ->setParameter('primary', $filter['primary'], Mapper::getParameterType($filter['primary']))
                            ->executeStatement();
                    }
                }
            }
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}