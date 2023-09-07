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

class V1Dot6Dot36 extends Base
{
    public function up(): void
    {
        // copy to root
        copy('vendor/atrocore/core/copy/.htaccess', '.htaccess');
        copy('vendor/atrocore/core/copy/index.php', 'index.php');

        $this->getPDO()->exec("UPDATE queue_item set priority='High' where priority='Crucial'");

        foreach (['composer.json', 'data/stable-composer.json'] as $filename) {
            if (!file_exists($filename)) {
                continue;
            }
            $data = json_decode(file_get_contents($filename), true);
            $data['require'] = array_merge($data['require'], ['atrocore/core' => '^1.6.36']);

            if (isset($data['scripts']['post-update-cmd'])) {
                $data['scripts']['post-update-cmd'] = '\\Atro\\Composer\\PostUpdate::postUpdate';
            }

            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // prepare composer.json
        $this->updateComposer('atrocore/core', '^1.6.36');

        // reload daemons
        file_put_contents('data/process-kill.txt', '1');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }
}
