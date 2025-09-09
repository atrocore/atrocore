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

class V2Dot0Dot38 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-05 14:00:00');
    }

    public function up(): void
    {
        $this->updateUiHandlers();
        $this->updateEntityDefs();
        $this->updateScopes();
    }

    protected function updateUiHandlers(): void
    {
        $path = 'data/reference-data/UiHandler.json';

        if (!file_exists($path)) {
            return;
        }

        $content = @file_get_contents($path);

        if (!empty($content)) {
            $content = str_replace('.sku', '.number', $content);
            $content = str_replace('"sku"', '"number"', $content);
            $content = str_replace('\\"sku\\"', '\\"number\\"', $content);
            $content = str_replace("'sku'", "'number'", $content);

            file_put_contents($path, $content);
        }
    }

    protected function updateEntityDefs(): void
    {
        $path = 'data/metadata/entityDefs/Product.json';

        if (file_exists($path)) {
            $customDefs = @json_decode(file_get_contents($path), true);

            if (!empty($customDefs['fields'])) {
                $toUpdate = false;

                if (!empty($customDefs['fields']['sku'])) {
                    $oldDefs = $customDefs['fields']['sku'];

                    if (is_array($oldDefs) && empty($oldDefs['isCustom'])) {
                        $newDefs = $customDefs['fields']['number'] ?? [];

                        unset($customDefs['fields']['sku']);

                        $customDefs['fields']['number'] = array_merge($oldDefs, $newDefs);

                        $toUpdate = true;
                    }
                }

                if ($toUpdate) {
                    file_put_contents($path, json_encode($customDefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
        }
    }

    protected function updateScopes(): void
    {
        $path = 'data/metadata/scopes/Product.json';

        if (file_exists($path)) {
            $customScopes = @json_decode(file_get_contents($path), true);

            if (is_array($customScopes)) {
                $toUpdate = false;

                foreach ($customScopes as $key => $value) {
                    if (is_string($value) && $value == 'sku') {
                        $customScopes[$key] = 'number';
                        $toUpdate = true;
                    }

                    if (is_array($value) && in_array('sku', $value)) {
                        $k = array_search('sku', $value);
                        if ($k !== false) {
                            $customScopes[$key][$k] = 'number';
                        }

                        $toUpdate = true;
                    }
                }

                if ($toUpdate) {
                    file_put_contents($path, json_encode($customScopes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
        }
    }
}
