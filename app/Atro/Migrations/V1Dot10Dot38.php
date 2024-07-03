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

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot10Dot38 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-02 17:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()){
            $this->exec("ALTER TABLE html_template RENAME TO preview_template;");
            $this->exec("CREATE TABLE preview_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', template TEXT DEFAULT NULL, entity_type VARCHAR(255) DEFAULT 'Product', data TEXT DEFAULT NULL, is_active BOOLEAN DEFAULT 'true' NOT NULL, PRIMARY KEY(id));
COMMENT ON COLUMN preview_template.data IS '(DC2Type:jsonObject)';");
        }else{
            $this->exec("RENAME table html_template TO preview_template;");
            $this->exec("CREATE TABLE preview_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, delete
d TINYINT(1) DEFAULT '0', template LONGTEXT DEFAULT NULL, entity_type VARCHAR(255) DEFAULT 'Pr
oduct', data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', is_active TINYINT(1) DEFAULT '1' NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
        }
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
