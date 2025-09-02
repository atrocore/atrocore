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
use Atro\Core\Utils\Metadata;

class V2Dot0Dot37 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-03 10:00:00');
    }

    public function up(): void
    {
        $fileName = 'data/reference-data/UiHandler.json';

        if (!file_exists($fileName)) {
            return;
        }

        $uiHandlers = json_decode(file_get_contents($fileName), true);
        if (!is_array($uiHandlers)) {
            return;
        }

        /** @var Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        // unset generated
        foreach ($metadata->get('entityDefs') ?? [] as $entityType => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }

            foreach ($entityDefs['fields'] ?? [] as $field => $fieldDefs) {
                if (empty($fieldDefs['conditionalProperties'])) {
                    continue;
                }

                foreach ($fieldDefs['conditionalProperties'] as $type => $fieldData) {
                    if (empty($fieldData['conditionGroup'])) {
                        continue;
                    }

                    switch ($type) {
                        case 'readOnly':
                            $typeId = 'ui_read_only';
                            break;
                        case 'visible':
                            $typeId = 'ui_visible';
                            break;
                        case 'required':
                            $typeId = 'ui_required';
                            break;
                        case 'disableOptions':
                            $typeId = 'ui_disable_options';
                            break;
                        default:
                            $typeId = null;
                    }

                    if (empty($typeId)) {
                        continue;
                    }

                    $code = md5("{$entityType}{$field}{$type}");

                    if (array_key_exists($code, $uiHandlers)) {
                        unset($uiHandlers[$code]);
                    }
                }
            }
        }

        // unset unexisting fields
        foreach ($uiHandlers as $code => $uiHandler) {
            foreach ($uiHandler['fields'] ?? [] as $field) {
                if (!$metadata->get("entityDefs.$uiHandler[entityType].fields.$field")) {
                    unset($uiHandlers[$code]['fields'][array_search($field, $uiHandlers[$code]['fields'])]);
                    if (empty($uiHandlers[$code]['fields'])) {
                        unset($uiHandlers[$code]);
                    }
                }
            }
        }

        echo '<pre>';
        print_r(count($uiHandlers));
        print_r($uiHandlers);
        die();
    }
}
