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

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Espo\Core\Utils\Metadata;

class V1Dot10Dot32 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-06-07 13:00:00');
    }

    public function up(): void
    {
        $path = 'data/metadata/entityDefs';
        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }

                $customDefs = json_decode(file_get_contents("$path/$file"), true);

                if (!empty($customDefs['fields'])) {
                    $toUpdate = false;
                    foreach ($customDefs['fields'] as $field => $fieldDefs) {
                        if (!empty($fieldDefs['rows'])) {
                            $customDefs['fields'][$field]['rowsMax'] = $customDefs['fields'][$field]['rows'];
                            unset($customDefs['fields'][$field]['rows']);
                            $toUpdate = true;
                        }
                    }
                    if ($toUpdate) {
                        file_put_contents("$path/$file", json_encode($customDefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    }
                }
            }
        }

        $this->updateComposer('atrocore/core', '^1.10.32');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }
}
