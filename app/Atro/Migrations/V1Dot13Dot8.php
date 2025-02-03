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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot13Dot8 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-03 12:00:00');
    }

    public function up(): void
    {
        $dir = "data/metadata/scopes";
        if (is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (in_array($item, ['.', '..'])) {
                    continue;
                }
                $fileName = "$dir/$item";
                if (file_exists($fileName)) {
                    $data = json_decode(file_get_contents($fileName), true);
                    $data['customizable'] = true;
                    file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
        }
    }
}
