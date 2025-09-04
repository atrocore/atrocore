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

class V2Dot0Dot38 extends Base
{
    public function up(): void
    {
        $path = 'data/metadata/entityDefs/Product.json';

        if (file_exists($path)) {
            $customDefs = @json_decode(file_get_contents($path), true);

            if (!empty($customDefs['fields']) && !empty($customDefs['fields']['sku'])) {
                $oldDefs = $customDefs['fields']['sku'];

                if (is_array($oldDefs) && empty($oldDefs['isCustom'])) {
                    $newDefs = $customDefs['fields']['number'] ?? [];

                    unset($customDefs['fields']['sku']);

                    $customDefs['fields']['number'] = array_merge($oldDefs, $newDefs);

                    file_put_contents($path, json_encode($customDefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
        }
    }
}
