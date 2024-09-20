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

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Utils\Util;

class V1Dot8Dot33 extends Base
{
    public function up(): void
    {
        $dirPath = 'custom/Espo/Custom/Resources/metadata/clientDefs';

        if (is_dir($dirPath)) {
            foreach (scandir($dirPath) as $file) {
                $entityType = str_replace('.json', '', $file);

                $filePath = $dirPath . '/' . $file;
                if (is_file($filePath)) {
                    $clientDefs = @json_decode(file_get_contents($filePath), true);
                    if (empty($clientDefs['dynamicLogic']['fields'])) {
                        continue;
                    }

                    foreach ($clientDefs['dynamicLogic']['fields'] as $field => $fieldConditions) {
                        foreach ($fieldConditions as $type => $fieldData) {
                            if (empty($fieldData['conditionGroup'])) {
                                continue;
                            }

                            $uniqueHash = md5("{$entityType}{$field}{$type}");

                            $this->getConnection()->createQueryBuilder()
                                ->delete('ui_handler')
                                ->where('hash = :hash')
                                ->setParameter('hash', $uniqueHash)
                                ->executeQuery();

                            $typeId = null;

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
                            }

                            $this->getConnection()->createQueryBuilder()
                                ->insert('ui_handler')
                                ->setValue('id', ':id')
                                ->setValue('name', ':name')
                                ->setValue('entity_type', ':entityType')
                                ->setValue('fields', ':fields')
                                ->setValue('type', ':type')
                                ->setValue('conditions_type', ':conditionsType')
                                ->setValue('conditions', ':conditions')
                                ->setValue('is_active', ':isActive')
                                ->setParameter('id', Util::generateUniqueHash())
                                ->setParameter('name', "Make field '{$field}' {$type}")
                                ->setParameter('entityType', $entityType)
                                ->setParameter('fields', json_encode([$field]))
                                ->setParameter('type', $typeId)
                                ->setParameter('conditionsType', 'basic')
                                ->setParameter('conditions', json_encode($fieldData))
                                ->setParameter('isActive', true)
                                ->executeQuery();
                        }
                    }

                    unset($clientDefs['dynamicLogic']['fields']);
                    if (empty($clientDefs['dynamicLogic']) && isset($clientDefs['dynamicLogic'])) {
                        unset($clientDefs['dynamicLogic']);
                    }

                    file_put_contents($filePath, json_encode($clientDefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
        }

        $this->updateComposer('atrocore/core', '^1.8.33');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }
}
