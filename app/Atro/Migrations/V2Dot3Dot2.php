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
use Atro\Core\Utils\Util;

class V2Dot3Dot2 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-05-15 10:00:00');
    }

    public function up(): void
    {
        foreach (['equal' => 'fieldEqual', 'similar' => 'fieldSimilar', 'contains' => 'fieldContains'] as $old => $new) {
            $this->getDbal()->createQueryBuilder()
                ->update('matching_rule')
                ->set('type', ':new')
                ->where('type = :old')
                ->setParameter('new', $new)
                ->setParameter('old', $old)
                ->executeQuery();
        }

        $this->exec("ALTER TABLE matching_rule ADD attribute_id VARCHAR(36) DEFAULT NULL");

        $this->migrateOptionsToMetadata();
    }

    // extensibleEnumId => [systemOptions, [Entity => fieldName]]
    private array $map = [
        'gender'                  => [['Male', 'Female', 'Neutral'],                                          ['User' => 'gender', 'Contact' => 'gender']],
        'role'                    => [['supplier', 'customer'],                                                ['Account' => 'role']],
        'addressType'             => [['billing', 'delivery'],                                                ['Address' => 'type']],
        'product_group_item_type' => [['physical_goods', 'services', 'digital_products', 'legal_rights'],     ['ProductGroup' => 'itemType']],
        'team_position'           => [[],                                                                     ['TeamUser' => 'role']],
        'update_type'             => [['basic', 'script'],                                                    ['Action' => 'updateType']],
        'content_items'           => [['highlight', 'top_features_list', 'story'],                            ['ContentItem' => 'type']],
        'listing_status'          => [[],                                                                     ['Listing' => 'status']],
        'pdfTemplateType'         => [['pdfTemplateHtml', 'pdfTemplateODT', 'pdfTemplateCatalog'],            ['PdfFeed' => 'type']],
        'budget_item_status'      => [[],                                                                     ['BudgetItem' => 'status']],
    ];

    private function migrateOptionsToMetadata(): void
    {
        $additionalLocales = $this->getAdditionalLocales();
        $translations      = $this->loadTranslations();

        foreach ($this->map as $enumId => [$systemOptions, $entityFields]) {
            if (empty($entityFields)) {
                continue;
            }

            foreach ($entityFields as $entity => $field) {
                $tableName = Util::toUnderScore(lcfirst($entity));
                $columnName = Util::toUnderScore(lcfirst($field));

                if ($this->isPgSQL()) {
                    $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " ALTER $columnName TYPE VARCHAR(255)");
                } else {
                    $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " CHANGE $columnName $columnName VARCHAR(255) DEFAULT NULL");
                }
            }

            $allOptions    = $this->getEnumOptionsWithNames($enumId, $additionalLocales);
            $customOptions = array_filter($allOptions, fn($row) => !in_array($row['id'], $systemOptions));

            if (empty($customOptions)) {
                continue;
            }

            // Write option IDs to data/metadata/entityDefs
            $ids = array_column(array_values($customOptions), 'id');
            foreach ($entityFields as $entity => $field) {
                $file = 'data/metadata/entityDefs/' . $entity . '.json';
                $data = file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
                $data['fields'][$field]['options'] = array_merge(['__APPEND__'], $ids);
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Write translations for each custom option into Translation.json
            foreach ($customOptions as $row) {
                $optionId = $row['id'];
                foreach ($entityFields as $entity => $field) {
                    $code = "$entity.options.$field.$optionId";
                    if (!isset($translations[$code])) {
                        $translations[$code] = [
                            'id'          => md5($code),
                            'code'        => $code,
                            'module'      => 'custom',
                            'isCustomized' => true,
                            'createdAt'   => date('Y-m-d H:i:s'),
                        ];
                    }
                    // Main name → enUs
                    $translations[$code]['enUs'] = $row['name'];
                    // Additional locales: name_de_de → deDe
                    foreach ($additionalLocales as $locale) {
                        $dbCol   = 'name_' . strtolower($locale);           // name_de_de
                        $jsonKey = $this->localeToCamel($locale);            // deDe
                        if (!empty($row[$dbCol])) {
                            $translations[$code][$jsonKey] = $row[$dbCol];
                        }
                    }
                }
            }
        }

        $this->saveTranslations($translations);
    }

    private function getAdditionalLocales(): array
    {
        $file = 'data/reference-data/Language.json';
        if (!file_exists($file)) {
            return [];
        }

        $locales = [];
        foreach (json_decode(file_get_contents($file), true) ?? [] as $row) {
            if (($row['role'] ?? '') === 'additional' && !empty($row['code'])) {
                $locales[] = $row['code'];
            }
        }

        return $locales;
    }

    private function loadTranslations(): array
    {
        $file = 'data/reference-data/Translation.json';

        return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
    }

    private function saveTranslations(array $translations): void
    {
        file_put_contents(
            'data/reference-data/Translation.json',
            json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function localeToCamel(string $locale): string
    {
        // 'de_DE' → 'deDe',  'uk_UA' → 'ukUa'
        $parts = explode('_', strtolower($locale));

        return $parts[0] . ucfirst($parts[1] ?? '');
    }

    private function getEnumOptionsWithNames(string $enumId, array $additionalLocales): array
    {
        try {
            $langCols = implode(', ', array_map(
                fn($l) => 'eeo.name_' . strtolower($l),
                $additionalLocales
            ));
            $select = 'eeo.id, eeo.name' . ($langCols ? ", $langCols" : '');

            $stmt = $this->getPDO()->prepare("
                SELECT $select
                FROM extensible_enum_option eeo
                INNER JOIN extensible_enum_extensible_enum_option eeeeo
                    ON eeeeo.extensible_enum_option_id = eeo.id
                WHERE eeeeo.extensible_enum_id = :enumId
                  AND eeo.deleted = false
                  AND eeeeo.deleted = false
                ORDER BY eeeeo.sorting ASC, eeo.id ASC
            ");
            $stmt->execute([':enumId' => $enumId]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
