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

class V1Dot6Dot56 extends Base
{
    public function up(): void
    {
        $this->exec("CREATE TABLE address (id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, name VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, description LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, phone VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, email VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(255) NOT NULL COLLATE `utf8mb4_unicode_ci`, street LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, zip VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, box VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, city VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, country VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, country_code VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, account_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_ACCOUNT_ID (account_id), INDEX IDX_ACCOUNT_ID_DELETED (account_id, deleted), INDEX IDX_CREATED_BY_ID (created_by_id), INDEX IDX_CREATED_BY_ID_DELETED (created_by_id, deleted), INDEX IDX_MODIFIED_BY_ID (modified_by_id), INDEX IDX_MODIFIED_BY_ID_DELETED (modified_by_id, deleted), INDEX IDX_NAME (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
        $this->exec("CREATE TABLE contact (id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, name VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, first_name VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, second_name VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, title VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, job_position VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, phone VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, mobile VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, email VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, street LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, zip VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, city VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, country VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, country_code VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_CREATED_BY_ID (created_by_id), INDEX IDX_CREATED_BY_ID_DELETED (created_by_id, deleted), INDEX IDX_MODIFIED_BY_ID (modified_by_id), INDEX IDX_MODIFIED_BY_ID_DELETED (modified_by_id, deleted), INDEX IDX_NAME (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
        $this->exec("CREATE TABLE contact_account (id INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`, account_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, contact_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, INDEX IDX_E71977259B6B5FBA (account_id), INDEX IDX_E7197725E7A1254A (contact_id), UNIQUE INDEX UNIQ_E71977259B6B5FBAE7A1254A (account_id, contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
        $this->exec("ALTER TABLE account ADD type VARCHAR(255) NOT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE account DROP type");
        $this->exec("DROP TABLE address");
        $this->exec("DROP TABLE contact");
        $this->exec("DROP TABLE contact_account");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
