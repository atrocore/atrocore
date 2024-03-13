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

class V1Dot3Dot40 extends Base
{
    public function up(): void
    {
        $this->execute("CREATE TABLE `locale` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `description` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_CREATED_BY_ID` (created_by_id), INDEX `IDX_MODIFIED_BY_ID` (modified_by_id), INDEX `IDX_NAME` (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("ALTER TABLE `locale` ADD language VARCHAR(255) DEFAULT 'en_US' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD date_format VARCHAR(255) DEFAULT 'MM/DD/YYYY' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD time_zone VARCHAR(255) DEFAULT 'UTC' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD week_start VARCHAR(255) DEFAULT 'sunday' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD time_format VARCHAR(255) DEFAULT 'HH:mm' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD thousand_separator VARCHAR(1) DEFAULT ',' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD decimal_mark VARCHAR(1) DEFAULT '.' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `portal` DROP language, DROP time_zone, DROP date_format, DROP time_format, DROP week_start, ADD locale_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE INDEX IDX_LOCALE_ID ON `portal` (locale_id)");

        $configData = include 'data/config.php';

        $language = !empty($configData['language']) ? $configData['language'] : 'en_US';
        $dateFormat = !empty($configData['dateFormat']) ? $configData['dateFormat'] : 'DD.MM.YYYY';
        $timeZone = !empty($configData['timeZone']) ? $configData['timeZone'] : 'UTC';
        $weekStart = !empty($configData['weekStart']) ? 'monday' : 'sunday';
        $timeFormat = !empty($configData['timeFormat']) ? $configData['timeFormat'] : 'HH:mm';
        $thousandSeparator = !empty($configData['thousandSeparator']) ? $configData['thousandSeparator'] : '.';
        $decimalMark = !empty($configData['decimalMark']) ? $configData['decimalMark'] : ',';

        $this->execute("INSERT INTO `locale` (id, name, language, date_format, time_zone, week_start, time_format, thousand_separator, decimal_mark) VALUES ('1', 'Main', '$language', '$dateFormat', '$timeZone', '$weekStart', '$timeFormat', '$thousandSeparator', '$decimalMark')");

        $this->getConfig()->set('localeId', '1');
        $this->getConfig()->save();
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
