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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot6Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-21 12:00:00');
    }

    public function up(): void
    {
        // the tables already exist where the PIM module is installed
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE brand (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, code VARCHAR(255) DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE INDEX IDX_BRAND_NAME ON brand (name, deleted)");
            $this->exec("CREATE INDEX IDX_BRAND_CREATED_BY_ID ON brand (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_BRAND_MODIFIED_BY_ID ON brand (modified_by_id, deleted)");

            $this->exec("CREATE TABLE brand_file (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, brand_id VARCHAR(36) DEFAULT NULL, file_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_BRAND_FILE_UNIQUE_RELATION ON brand_file (deleted, brand_id, file_id)");
            $this->exec("CREATE INDEX IDX_BRAND_FILE_CREATED_BY_ID ON brand_file (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_BRAND_FILE_MODIFIED_BY_ID ON brand_file (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_BRAND_FILE_BRAND_ID ON brand_file (brand_id, deleted)");
            $this->exec("CREATE INDEX IDX_BRAND_FILE_FILE_ID ON brand_file (file_id, deleted)");

            $this->exec("ALTER TABLE product ADD brand_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_PRODUCT_BRAND_ID ON product (brand_id, deleted)");
        } else {
            $this->exec("CREATE TABLE brand (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', description LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, code VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, INDEX IDX_BRAND_NAME (name, deleted), INDEX IDX_BRAND_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_BRAND_MODIFIED_BY_ID (modified_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE brand_file (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, brand_id VARCHAR(36) DEFAULT NULL, file_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_BRAND_FILE_UNIQUE_RELATION (deleted, brand_id, file_id), INDEX IDX_BRAND_FILE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_BRAND_FILE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_BRAND_FILE_BRAND_ID (brand_id, deleted), INDEX IDX_BRAND_FILE_FILE_ID (file_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");

            $this->exec("ALTER TABLE product ADD brand_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_PRODUCT_BRAND_ID ON product (brand_id, deleted)");
        }

        // multilingual columns and the unique code index depend on the installation and are added by the schema rebuild
        $this->rebuild();
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
