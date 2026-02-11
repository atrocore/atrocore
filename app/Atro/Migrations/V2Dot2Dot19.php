<?php
/*
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
use Atro\Core\Utils\Util;

class V2Dot2Dot19 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-02-11 10:00:00');
    }

    public function up(): void
    {
        $dir = 'data/metadata/scopes';

        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (!in_array($item, ['.', '..'])) {
                    $parts = explode('.', $item);
                    $scope = $parts[0];

                    $content = @json_decode(file_get_contents($dir . '/' . $item), true);
                    if (!empty($content) && !empty($content['primaryEntityId'])) {
                        $tableName = Util::toUnderScore(lcfirst($scope));
                        if ($this->isPgSQL()) {
                            $this->exec("DROP INDEX idx_{$tableName}_golden_record_id;");
                        } else {
                            $this->exec("DROP INDEX IDX_{$tableName}_GOLDEN_RECORD_ID ON $tableName;");
                        }
                        $this->exec("ALTER TABLE $tableName RENAME COLUMN golden_record_id TO master_record_id;");
                        $this->exec("CREATE INDEX IDX_" . strtoupper($tableName) . "_MASTER_RECORD_ID ON $tableName (master_record_id, deleted);");
                    }
                }
            }
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
