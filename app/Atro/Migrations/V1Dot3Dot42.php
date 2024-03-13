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

class V1Dot3Dot42 extends Base
{
    public function up(): void
    {
        $this->execute("CREATE TABLE `locale_measure` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `locale_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `measure_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_DCC4988CE559DFD1` (locale_id), INDEX `IDX_DCC4988C5DA37D00` (measure_id), UNIQUE INDEX `UNIQ_DCC4988CE559DFD15DA37D00` (locale_id, measure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("ALTER TABLE `measure` ADD data MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("DROP INDEX id ON `locale_measure`");
        $this->execute("ALTER TABLE `unit` ADD is_default TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD multiplier DOUBLE PRECISION DEFAULT '1' COLLATE utf8mb4_unicode_ci, ADD convert_to_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE INDEX IDX_CONVERT_TO_ID ON `unit` (convert_to_id)");
        $this->execute("ALTER TABLE `unit` DROP INDEX IDX_NAME, ADD UNIQUE INDEX UNIQ_DCBB0C535E237E06EB3B4E33 (name, deleted)");
        $this->execute("CREATE UNIQUE INDEX UNIQ_DCBB0C5333E7211DEB3B4E33 ON `unit` (name_de_de, deleted)");

        $this->execute("UPDATE `unit` SET multiplier=1 WHERE deleted=0");

        $units = $this->getPDO()->query("SELECT * FROM `unit` WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);

        $measures = [];
        foreach ($units as $unit) {
            if (!in_array($unit['measure_id'], $measures)) {
                $measures[] = $unit['measure_id'];
                $this->execute("UPDATE `unit` SET is_default=1 WHERE id='{$unit['id']}'");
            }
        }
    }

    public function down(): void
    {
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
