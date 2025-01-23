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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Atro\ORM\DB\RDB\Mapper;

class V1Dot13Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-01-22 15:00:00');
    }

    public function up(): void
    {
        $this->exec('ALTER TABLE "user" ADD layout_profile_id VARCHAR(36) DEFAULT NULL;');
        $this->exec('CREATE INDEX IDX_USER_LAYOUT_PROFILE_ID ON "user" (layout_profile_id, deleted)');

        try {
            $preferences = $this->getConnection()->createQueryBuilder()
                ->select('id', 'data')
                ->from('preferences')
                ->fetchAllAssociative();

            foreach ($preferences as $preference) {
                $data = @json_decode($preference['data'], true);
                if (empty($data['layoutProfileId'])) {
                    continue;
                }

                $this->getConnection()->createQueryBuilder()
                    ->update('user')
                    ->set('layout_profile_id', ':layoutProfileId')
                    ->where('id= :id')
                    ->setParameter('layoutProfileId', $data['layoutProfileId'])
                    ->setParameter('id', $preference['id'])
                    ->executeStatement();
            }
        } catch (\Throwable $e) {
        }

        // delete all custom layout
        try {
            $ids = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('layout')
                ->where('preferences_id is not null')
                ->fetchFirstColumn();
        } catch (\Exception $e) {
            $ids = [];
        }

        $this->removeAllLayouts($ids);

        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX idx_layout_preferences;");
            $this->exec("ALTER TABLE layout DROP preferences_id");
        } else {
            $this->exec("DROP INDEX IDX_LAYOUT_PREFERENCES ON layout;");
            $this->exec("ALTER TABLE layout DROP preferences_id");
        }

        // delete all small layouts
        $types = ["listSmall", "detailSmall", "sidePanelsDetailSmall", "sidePanelsEdit", "sidePanelsEditSmall"];
        $ids = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from('layout')
            ->where('view_type in (:types)')
            ->setParameter('types', $types, Mapper::getParameterType($types))
            ->fetchFirstColumn();

        $this->removeAllLayouts($ids);


        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE layout ADD related_entity VARCHAR(255) DEFAULT NULL");
            $this->exec("DROP INDEX IDX_LAYOUT_LAYOUT_PROFILE;");
            $this->exec("CREATE UNIQUE INDEX IDX_LAYOUT_LAYOUT_PROFILE ON layout (layout_profile_id, entity, related_entity, view_type, deleted)");
            $this->exec("ALTER TABLE layout_profile ADD parent_id VARCHAR(36) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_LAYOUT_PROFILE_PARENT_ID ON layout_profile (parent_id, deleted)");
        } else {
            $this->exec("ALTER TABLE layout ADD related_entity VARCHAR(255) DEFAULT NULL");
            $this->exec("DROP INDEX IDX_LAYOUT_LAYOUT_PROFILE ON layout;");
            $this->exec("CREATE UNIQUE INDEX IDX_LAYOUT_LAYOUT_PROFILE ON layout (layout_profile_id, entity, related_entity, view_type, deleted);");
            $this->exec("ALTER TABLE layout_profile ADD parent_id VARCHAR(36) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_LAYOUT_PROFILE_PARENT_ID ON layout_profile (parent_id, deleted);");
        }


        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE user_entity_layout (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', entity VARCHAR(255) DEFAULT NULL, view_type VARCHAR(255) DEFAULT NULL, related_entity VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, layout_profile_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX IDX_USER_ENTITY_LAYOUT_UNIQUE ON user_entity_layout (user_id, entity, view_type, related_entity, layout_profile_id, deleted);");
            $this->exec("CREATE INDEX IDX_USER_ENTITY_LAYOUT_USER_ID ON user_entity_layout (user_id, deleted);");
            $this->exec("CREATE INDEX IDX_USER_ENTITY_LAYOUT_MODIFIED_BY_ID ON user_entity_layout (modified_by_id, deleted)");
        } else {
            $this->exec("CREATE TABLE user_entity_layout (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', entity VARCHAR(255) DEFAULT NULL, view_type VARCHAR(255) DEFAULT NULL, related_entity VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, layout_profile_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_ENTITY_LAYOUT_UNIQUE (user_id, entity, view_type, related_entity, layout_profile_id, deleted), INDEX IDX_USER_ENTITY_LAYOUT_USER_ID (user_id, deleted), INDEX IDX_USER_ENTITY_LAYOUT_MODIFIED_BY_ID (modified_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    protected function removeAllLayouts($ids)
    {
        if (empty($ids)) {
            return;
        }

        try {
            $sectionIds = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('layout_section')
                ->where('layout_id in (:layout_id)')
                ->setParameter('layout_id', $ids, Mapper::getParameterType($ids))
                ->fetchFirstColumn();

            $this->getConnection()->createQueryBuilder()
                ->delete('layout_row_item')
                ->where('section_id in (:section_id)')
                ->setparameter('section_id', $sectionIds, Mapper::getParameterType($sectionIds))
                ->executeStatement();

            foreach (['layout_side_panel_item', 'layout_section', 'layout_relationship_item', 'layout_list_item'] as $table) {
                $this->getConnection()->createQueryBuilder()
                    ->delete($table)
                    ->where('layout_id in (:layout_id)')
                    ->setparameter('layout_id', $ids, Mapper::getParameterType($ids))
                    ->executeStatement();
            }

            $this->getConnection()->createQueryBuilder()
                ->delete('layout')
                ->where('id in (:layout_id)')
                ->setparameter('layout_id', $ids, Mapper::getParameterType($ids))
                ->executeStatement();
        } catch (\Exception $e) {

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
