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

class V2Dot2Dot20 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-02-11 12:00:00');
    }

    public function up(): void
    {
        $path = 'data/metadata/scopes';

        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }

                $scopeData = @json_decode(file_get_contents("$path/$file"), true);

                if (!empty($scopeData['unInheritedFields']) || !empty($scopeData['unInheritedRelations'])) {
                    $uninherited = array_merge($scopeData['unInheritedFields'] ?? [], $scopeData['unInheritedRelations'] ?? []);

                    $entityDefsPath = 'data/metadata/entityDefs/' . $file;
                    $entityDefsData = file_exists($entityDefsPath) ? @json_decode(file_get_contents($entityDefsPath), true) : [];

                    foreach ($uninherited as $field) {
                        $entityDefsData['fields'][$field]['inheritanceDisabled'] = true;
                    }

                    file_put_contents($entityDefsPath, json_encode($entityDefsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
        }
    }
}
