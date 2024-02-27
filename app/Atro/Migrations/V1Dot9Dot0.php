<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot9Dot0 extends Base
{
    public function up(): void
    {
        foreach (['composer.json', 'data/stable-composer.json'] as $filename) {
            if (!file_exists($filename)) {
                continue;
            }
            $data = json_decode(file_get_contents($filename), true);
            $data['require'] = array_merge($data['require'], ['atrocore/core' => '^1.9.0']);
            if (isset($data['require']['atrocore/dam'])) {
                unset($data['require']['atrocore/dam']);
            }
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if (file_exists('data/modules.json')) {
            $modules = @json_decode(file_get_contents('data/modules.json'), true);
            if (!empty($modules)) {
                $newModules = [];
                foreach ($modules as $module) {
                    if ($module !== 'Dam') {
                        $newModules[] = $module;
                    }
                }
                file_put_contents('data/modules.json', json_encode($newModules));
            }
        }
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }
}
