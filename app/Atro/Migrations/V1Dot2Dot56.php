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

use Atro\Core\Utils\Util;
use Atro\Core\Migration\Base;

/**
 * Migration class for version 1.2.56
 */
class V1Dot2Dot56 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $multilangFields = "";
        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $locale = strtolower($locale);

                $multilangFields .= ", `name_" . $locale . "` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci";
                $multilangFields .= ", `description_" . $locale . "` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci";
                $multilangFields .= ", `html_" . $locale . "` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci";
            }
        }
        $this->execute("CREATE TABLE `info_page` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `description` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `html` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `css` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci {$multilangFields}, INDEX `IDX_NAME` (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->execute("DROP TABLE `info_page`");
    }

    /**
     * @param string $sql
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
