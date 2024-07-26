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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot10Dot49 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-29 11:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()){
            $this->exec("CREATE TABLE notification_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', code VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, data TEXT DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX UNIQ_C270272677153098EB3B4E33 ON notification_template (code, deleted);");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_TEMPLATE_NAME ON notification_template (name, deleted);");
            $this->exec("COMMENT ON COLUMN notification_template.data IS '(DC2Type:jsonObject)'");
            $this->exec("CREATE TABLE notification_profile (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', description TEXT DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, PRIMARY KEY(id))");

            $this->exec("CREATE TABLE notification_rule (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', description VARCHAR(255) DEFAULT NULL, entity VARCHAR(255) DEFAULT NULL, occurrence VARCHAR(255) DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, ignore_self_action BOOLEAN DEFAULT 'false' NOT NULL, as_owner BOOLEAN DEFAULT 'false' NOT NULL, as_follower BOOLEAN DEFAULT 'false' NOT NULL, as_assignee BOOLEAN DEFAULT 'false' NOT NULL, as_team_member BOOLEAN DEFAULT 'false' NOT NULL, as_notification_profile BOOLEAN DEFAULT 'false' NOT NULL, data TEXT DEFAULT NULL, notification_profile_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX IDX_NOTIFICATION_RULE_UNIQUE_NOTIFICATION_RULES ON notification_rule (notification_profile_id, entity, occurrence);");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_RULE_NOTIFICATION_PROFILE_ID ON notification_rule (notification_profile_id, deleted);");
            $this->exec("COMMENT ON COLUMN notification_rule.data IS '(DC2Type:jsonObject)';");
        }else{
            $this->exec("CREATE TABLE notification_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', code VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', UNIQUE INDEX UNIQ_C270272677153098EB3B4E33 (code, deleted), INDEX IDX_NOTIFICATION_TEMPLATE_NAME (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
            $this->exec("CREATE TABLE notification_profile (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', description LONGTEXT DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
            $this->exec("CREATE TABLE notification_rule (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', description VARCHAR(255) DEFAULT NULL, entity VARCHAR(255) DEFAULT NULL, occurrence VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, ignore_self_action TINYINT(1) DEFAULT '0' NOT NULL, as_owner TINYINT(1) DEFAULT '0' NOT NULL, as_follower TINYINT(1) DEFAULT '0' NOT NULL, as_assignee TINYINT(1) DEFAULT '0' NOT NULL, as_team_member TINYINT(1) DEFAULT '0' NOT NULL, as_notification_profile TINYINT(1) DEFAULT '0' NOT NULL, data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', notification_profile_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX IDX_NOTIFICATION_RULE_UNIQUE_NOTIFICATION_RULES (notification_profile_id, entity, occurrence), INDEX IDX_NOTIFICATION_RULE_NOTIFICATION_PROFILE_ID (notification_profile_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
        }

        $this->getConfig()->set('sendOutNotifications', !$this->getConfig()->get('disableEmailDelivery'));
    }

    public function down(): void
    {
        $this->exec("DROP TABLE notification_rule;");
        $this->exec("DROP TABLE notification_profile;");
        $this->exec("DROP TABLE notification_template;");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
