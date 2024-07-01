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

class V1Dot10Dot35 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-06-25 15:00:00');
    }

    public function up(): void
    {
        $dir = 'data/metadata/scopes';
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $fileName) {
            if (in_array($fileName, ['.', '..'])) {
                continue;
            }

            $contents = @file_get_contents("$dir/$fileName");
            if (empty($contents)) {
                continue;
            }

            $data = @json_decode($contents, true);
            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $k => $v) {
                if ($v === null) {
                    unset($data[$k]);
                }
            }

            file_put_contents("$dir/$fileName", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    public function down(): void
    {
    }
}
