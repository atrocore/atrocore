<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot4Dot0 extends Base
{
    public function up(): void
    {
        $existsUnit = [];
        foreach ($this->getPDO()->query("SELECT * FROM `unit` WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC) as $unit) {
            $key = "{$unit['measure_id']}_{$unit['name']}_{$unit['deleted']}";
            if (isset($existsUnit[$key])) {
                $this->execute("DELETE FROM `unit` WHERE id='{$unit['id']}'");
            }
            $existsUnit[$key] = true;
        }

        $this->execute("ALTER TABLE `unit` DROP INDEX UNIQ_DCBB0C535E237E06EB3B4E33, ADD INDEX IDX_NAME (name, deleted)");
        $this->execute("DROP INDEX UNIQ_DCBB0C5333E7211DEB3B4E33 ON `unit`");
        $this->execute("CREATE UNIQUE INDEX UNIQ_DCBB0C535DA37D005E237E06EB3B4E33 ON `unit` (measure_id, name, deleted)");

        $this->execute("ALTER TABLE `user` ADD portal_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE INDEX IDX_PORTAL_ID ON `user` (portal_id)");

        try {
            $data = $this
                ->getPDO()
                ->query("SELECT * FROM `portal_user` WHERE deleted=0")
                ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $data = [];
        }

        foreach ($data as $v) {
            $this->execute("UPDATE `user` SET portal_id='{$v['portal_id']}' WHERE id='{$v['user_id']}'");
        }

        $this->execute("DROP TABLE portal_user");

        $this->execute("ALTER TABLE `user` ADD account_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE INDEX IDX_ACCOUNT_ID ON `user` (account_id)");

        try {
            $data = $this
                ->getPDO()
                ->query("SELECT * FROM `account_portal_user` WHERE deleted=0")
                ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $data = [];
        }

        foreach ($data as $v) {
            $this->execute("UPDATE `user` SET account_id='{$v['account_id']}' WHERE id='{$v['user_id']}'");
        }

        $this->execute("DROP TABLE account_portal_user");
    }

    public function down(): void
    {
        $this->execute(
            "CREATE TABLE `portal_user` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `portal_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `user_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_76511E4B887E1DD` (portal_id), INDEX `IDX_76511E4A76ED395` (user_id), UNIQUE INDEX `UNIQ_76511E4B887E1DDA76ED395` (portal_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB"
        );
        $this->execute(
            "CREATE TABLE `account_portal_user` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `user_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `account_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_D622EDE7A76ED395` (user_id), INDEX `IDX_D622EDE79B6B5FBA` (account_id), UNIQUE INDEX `UNIQ_D622EDE7A76ED3959B6B5FBA` (user_id, account_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB"
        );

        $this->execute("ALTER TABLE `unit` DROP INDEX IDX_NAME, ADD UNIQUE INDEX UNIQ_DCBB0C535E237E06EB3B4E33 (name, deleted)");
        $this->execute("DROP INDEX UNIQ_DCBB0C535DA37D005E237E06EB3B4E33 ON `unit`");
        $this->execute("CREATE UNIQUE INDEX UNIQ_DCBB0C5333E7211DEB3B4E33 ON `unit` (name_de_de, deleted)");
    }

    protected function execute(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
