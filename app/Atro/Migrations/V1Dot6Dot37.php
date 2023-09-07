<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot6Dot37 extends Base
{
    public function up(): void
    {
        copy('vendor/atrocore/core/copy/.htaccess', '.htaccess');

        foreach (['composer.json', 'data/stable-composer.json'] as $filename) {
            if (!file_exists($filename)) {
                continue;
            }
            $data = json_decode(file_get_contents($filename), true);
            $data['require'] = array_merge($data['require'], ['atrocore/core' => '^1.6.37']);

            if (isset($data['scripts']['post-update-cmd'])) {
                $data['scripts']['post-update-cmd'] = '\\Atro\\Composer\\PostUpdate::postUpdate';
            }

            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        foreach (['data/composer.log', 'data/treo-composer.log', 'data/atro-composer.log'] as $filename) {
            if (!file_exists($filename)) {
                continue;
            }
            unlink($filename);
        }

        file_put_contents('data/process-kill.txt', '1');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }
}
