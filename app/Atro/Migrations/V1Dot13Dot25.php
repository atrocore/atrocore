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

class V1Dot13Dot25 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-25 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE \"user\" ADD dashboard_layout TEXT DEFAULT NULL");
            $this->exec("ALTER TABLE \"user\" ADD dashlets_options TEXT DEFAULT NULL");
            $this->exec("ALTER TABLE \"user\" ADD preset_filters TEXT DEFAULT NULL");
            $this->exec("ALTER TABLE \"user\" ADD use_custom_tab_list BOOLEAN DEFAULT 'false' NOT NULL");
            $this->exec("ALTER TABLE \"user\" ADD follow_entity_on_stream_post BOOLEAN DEFAULT 'true' NOT NULL");
            $this->exec("ALTER TABLE \"user\" ADD follow_created_entities BOOLEAN DEFAULT 'false' NOT NULL");
            $this->exec("ALTER TABLE \"user\" ADD closed_panel_options TEXT DEFAULT NULL");
            $this->exec("ALTER TABLE \"user\" ADD receive_notifications BOOLEAN DEFAULT 'false' NOT NULL");
            $this->exec("ALTER TABLE \"user\" ADD favorites_list TEXT DEFAULT NULL");
            $this->exec("ALTER TABLE \"user\" ADD locale_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE \"user\" ADD style_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE \"user\" ADD notification_profile_id VARCHAR(36) DEFAULT NULL");
            $this->exec("COMMENT ON COLUMN \"user\".dashboard_layout IS '(DC2Type:jsonArray)'");
            $this->exec("COMMENT ON COLUMN \"user\".dashlets_options IS '(DC2Type:jsonObject)'");
            $this->exec("COMMENT ON COLUMN \"user\".preset_filters IS '(DC2Type:jsonObject)'");
            $this->exec("COMMENT ON COLUMN \"user\".closed_panel_options IS '(DC2Type:jsonObject)'");
            $this->exec("COMMENT ON COLUMN \"user\".favorites_list IS '(DC2Type:jsonArray)'");
            $this->exec("CREATE INDEX IDX_USER_NOTIFICATION_PROFILE_ID ON \"user\" (notification_profile_id, deleted)");
        } else {
            $this->exec("ALTER TABLE user ADD dashboard_layout LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)', ADD dashlets_options LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', ADD preset_filters LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', ADD use_custom_tab_list TINYINT(1) DEFAULT '0' NOT NULL, ADD follow_entity_on_stream_post TINYINT(1) DEFAULT '1' NOT NULL, ADD follow_created_entities TINYINT(1) DEFAULT '0' NOT NULL, ADD closed_panel_options LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', ADD receive_notifications TINYINT(1) DEFAULT '0' NOT NULL, ADD favorites_list LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)', ADD locale_id VARCHAR(36) DEFAULT NULL, ADD style_id VARCHAR(36) DEFAULT NULL, ADD notification_profile_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_USER_NOTIFICATION_PROFILE_ID ON user (notification_profile_id, deleted)");
        }

        try {
            $preferences = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('preferences')
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $preferences = [];
        }

        foreach ($preferences as $record) {
            if ($record['id'] === 'system') {
                continue;
            }
            $data = @json_decode($record['data'], true);
            if (empty($data)) {
                continue;
            }

            foreach ($data as $field => $value) {
                if ($field === 'id' || $field === 'name' || empty($value)) {
                    continue;
                }

                $column = Util::toUnderScore($field);
                if ($column === 'locale') {
                    $column = 'locale_id';
                }

                if (is_array($value)) {
                    $value = json_encode($value);
                }

                try {
                    $this->getConnection()->createQueryBuilder()
                        ->update($this->getConnection()->quoteIdentifier('user'))
                        ->set($this->getConnection()->quoteIdentifier($column), ':value')
                        ->where('id=:id')
                        ->setParameter('id', $record['id'])
                        ->setParameter('value', $value, Mapper::getParameterType($value))
                        ->executeQuery();
                } catch (\Throwable $e) {
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