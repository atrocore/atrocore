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

class V1Dot10Dot38 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-03 12:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()){
            $this->exec("ALTER TABLE html_template RENAME TO preview_template;");
            $this->exec("CREATE TABLE preview_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', template TEXT DEFAULT NULL, entity_type VARCHAR(255) DEFAULT 'Product', data TEXT DEFAULT NULL, is_active BOOLEAN DEFAULT 'true' NOT NULL, PRIMARY KEY(id))");
            $this->exec("COMMENT ON COLUMN preview_template.data IS '(DC2Type:jsonObject)';");
        }else{
            $this->exec("RENAME table html_template TO preview_template;");
            $this->exec("CREATE TABLE preview_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', template LONGTEXT DEFAULT NULL, entity_type VARCHAR(255) DEFAULT 'Product', data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', is_active TINYINT(1) DEFAULT '1' NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }

        self::removeDir('custom');
        self::removeDir('dump/custom');
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

    protected static function scanDir(string $dir): array
    {
        // prepare result
        $result = [];

        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (!in_array($item, ['.', '..'])) {
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    protected static function removeDir(string $dir): void
    {
        if (file_exists($dir) && is_dir($dir)) {
            foreach (self::scanDir($dir) as $object) {
                if (is_dir($dir . "/" . $object)) {
                    self::removeDir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }
}
