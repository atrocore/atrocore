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

class V1Dot8Dot2 extends Base
{
    public function up(): void
    {
        $folderPath = 'custom/Espo/Custom/Resources/metadata/scopes';

        if (is_dir($folderPath)) {
            foreach (scandir($folderPath) as $fileName) {
                if (is_file($folderPath . '/' . $fileName)) {
                    $contents = file_get_contents($folderPath . '/' . $fileName);
                    if (strpos($contents, '"Relationship"')) {
                        $contents = str_replace('"Relationship"', '"Base"', $contents);
                        file_put_contents($folderPath . '/' . $fileName, $contents);
                    }
                }
            }
        }
    }

    public function down(): void
    {
    }
}
