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

declare(strict_types=1);

namespace Atro\Console;

use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Utils\Util;

class RegenerateUiHandlers extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Regenerate UI handlers.';
    }

    public function run(array $data): void
    {
        $this->refresh();
        $this->getContainer()->get('dataManager')->clearCache();

        self::show('UI handlers regenerated successfully.', self::SUCCESS);
    }

    public function refresh(): void
    {
        $this->getMemoryStorage()->set('ignorePushUiHandler', true);
        $clientDefsData = $this->getMetadata()->get('clientDefs', []);
        $this->getMemoryStorage()->set('ignorePushUiHandler', false);

        $data = $this->getConfig()->get('referenceData.UiHandler') ?? [];

        foreach ($data as $code => $row) {
            if (!empty($row['system'])) {
                unset($data[$code]);
            }
        }

        foreach ($clientDefsData as $entityType => $clientDefs) {
            if (empty($clientDefs['dynamicLogic']['fields']) && empty($clientDefs['dynamicLogic']['relationships'])) {
                continue;
            }

            foreach ($clientDefs['dynamicLogic']['fields'] ?? [] as $field => $fieldConditions) {
                foreach ($fieldConditions as $type => $fieldData) {
                    if (empty($fieldData['conditionGroup'])) {
                        continue;
                    }

                    if ($type === 'disableOptions' && empty($fieldData['disabledOptions'])) {
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
                    $data[$code] = [
                        'id'             => Util::generateId(),
                        'name'           => "Make field '{$field}' {$type}",
                        'code'           => md5("{$entityType}{$field}{$type}"),
                        'entityType'     => $entityType,
                        'fields'         => [$field],
                        'triggerAction'  => 'ui_on_change',
                        'type'           => $typeId,
                        'conditionsType' => 'basic',
                        'conditions'     => json_encode($fieldData),
                        'isActive'       => true,
                        'system'         => true,
                        'createdAt'      => date('Y-m-d H:i:s'),
                        'createdById'    => 'system',
                    ];

                    if ($typeId === 'ui_disable_options') {
                        $data[$code]['disabledOptions'] = $fieldData['disabledOptions'];
                    }
                }
            }

            foreach ($clientDefs['dynamicLogic']['relationships'] ?? [] as $relationship => $relationshipConditions) {
                foreach ($relationshipConditions as $type => $relationshipData) {
                    if (empty($fieldData['conditionGroup'])) {
                        continue;
                    }

                    if ($type !== 'visible') {
                        continue;
                    }

                    $typeId = 'ui_visible';


                    $code = md5("Relationship-{$entityType}{$relationship}{$type}");
                    $data[$code] = [
                        'id'             => Util::generateId(),
                        'name'           => "Make panel '{$relationship}' {$type}",
                        'code'           => md5("Relationship-{$entityType}{$relationship}{$type}"),
                        'entityType'     => $entityType,
                        'relationships'  => [$relationship],
                        'triggerAction'  => 'ui_on_change',
                        'type'           => $typeId,
                        'conditionsType' => 'basic',
                        'conditions'     => json_encode($relationshipData),
                        'isActive'       => true,
                        'system'         => true,
                        'createdAt'      => date('Y-m-d H:i:s'),
                        'createdById'    => 'system',
                    ];
                }
            }
        }

        @mkdir('data/reference-data');

        file_put_contents('data/reference-data/UiHandler.json', json_encode($data));
    }

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->getContainer()->get('memoryStorage');
    }
}
