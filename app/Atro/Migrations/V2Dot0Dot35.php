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
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;

class V2Dot0Dot35 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-25 10:00:00');
    }

    public function up(): void
    {
        $path = 'data/metadata/entityDefs';
        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }

                $scope = explode('.', $file)[0];
                $customDefs = @json_decode(file_get_contents("$path/$file"), true);
                $keyTranslations = [];
                if (!empty($customDefs['fields'])) {
                    $toUpdate = false;
                    foreach ($customDefs['fields'] as $field => $fieldDefs) {
                        if (!empty($fieldDefs['optionsIds'])) {
                           // default value should use optionsIds
                            if(!empty($fieldDefs['default']) && !empty($fieldDefs['options'])) {
                                $key = array_search($fieldDefs['default'], $fieldDefs['options']);
                                if($key !== false && !empty($fieldDefs['optionsIds'][$key])) {
                                    $customDefs['fields'][$field]['default'] = $fieldDefs['optionsIds'][$key];
                                }
                            }
                            // we should define the default translation as old options (or the label will be keys in optionsIds instead)
                            foreach ($fieldDefs['optionsIds'] as $key => $optionId) {
                                if (!isset($fieldDefs['options'][$key])) {
                                    continue;
                                }
                                $keyTranslations[$scope . '.options.' . $field . '.' . $optionId] = $fieldDefs['options'][$key];
                            }
                            $customDefs['fields'][$field]['options'] = $fieldDefs['optionsIds'];
                            // unset($customDefs['fields'][$field]['optionsIds']);
                            $toUpdate = true;
                        }
                    }
                    if ($toUpdate) {
                        file_put_contents("$path/$file", json_encode($customDefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    }
                }

                $toUpdate = false;
                if (!empty($keyTranslations)) {
                    $translationPath = 'data/reference-data/Translation.json';
                    $localePath = 'data/reference-data/Locale.json';

                    $translations = @json_decode(file_get_contents($translationPath), true) ?? [];
                    $codes = array_column($translations, 'code');
                    foreach ($keyTranslations as $code => $value) {
                        if (in_array($code, $codes)) {
                            //already exists no need to create
                            unset($keyTranslations[$code]);
                        }
                    }

                    if (!empty($keyTranslations)) {
                        $languages = ['en_US', 'de_DE', 'uk_UA'];
                        $locales = @json_decode(file_get_contents($localePath), true);
                        if (!empty($locales)) {
                            foreach ($locales as $locale) {
                                if (empty($locale['code'])) {
                                    continue;
                                }
                                if (!in_array($locale['code'], $languages)) {
                                    $languages[] = $locale['code'];
                                }
                            }
                        }

                        foreach ($keyTranslations as $code => $value) {
                            $translations[$code] = [
                                "id" => IdGenerator::uuid(),
                                "code" => $code,
                                "isCustomized" => true,
                                "createdAt" => date('Y-m-d H:i:s'),
                                "createdById" => "system",
                                "module" => "custom"
                            ];

                            foreach ($languages as $language) {
                                $field = Util::toCamelCase(strtolower($language));
                                $translations[$code][$field] = $value;
                                $toUpdate = true;
                            }
                        }
                    }

                    if ($toUpdate) {
                        file_put_contents($translationPath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    }
                }
            }
        }

        // update display format option
        $options  = [
            "1" => "format1",
            "2" => "format2",
        ];

        foreach ($options as $old => $new) {
            $this->getConnection()->createQueryBuilder()
                ->update('measure')
                ->set('display_format', ':new')
                ->where('display_format = :old')
                ->setParameter('new', $new)
                ->setParameter('old', $old)
                ->executeQuery();
        }
    }
}
