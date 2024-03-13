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

/**
 * Migration class for version 1.2.61
 */
class V1Dot2Dot61 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $sql = "ADD description_%s MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci";

        $this->updateUnitDescriptionField($sql);
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $sql = "DROP description_%s";

        $this->updateUnitDescriptionField($sql);
    }

    /**
     * @param string $sql
     */
    protected function updateUnitDescriptionField(string $sql): void
    {
        if ($this->getConfig()->get('isMultilangActive', false)) {
            $locales = $this->getConfig()->get('inputLanguageList', []);

            if (!empty($locales)) {
                $parts = [];

                foreach ($locales as $locale) {
                    $parts[] = sprintf($sql, strtolower($locale));
                }

                if (!empty($parts)) {
                    $fields = implode(', ', $parts);

                    $sql = "ALTER TABLE `unit` $fields";
                    $this->execute($sql);
                }
            }
        }
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
