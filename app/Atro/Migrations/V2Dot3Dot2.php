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

    private function migrateOptionsToMetadata(): void
    {
        // extensibleEnumId => [systemOptions, [Entity => fieldName]]
        $map = [
            'gender'                  => [['Male', 'Female', 'Neutral'], ['User' => 'gender', 'Contact' => 'gender']],
            'role'                    => [['supplier', 'customer'], ['Account' => 'role']],
            'addressType'             => [['billing', 'delivery'], ['Address' => 'type']],
            'product_group_item_type' => [['physical_goods', 'services', 'digital_products', 'legal_rights'], ['ProductGroup' => 'itemType']],
            'team_position'           => [[], ['TeamUser' => 'role']],
            'update_type'             => [['basic', 'script'], ['Action' => 'updateType']],
            'content_items'           => [['highlight', 'top_features_list', 'story'], ['ContentItem' => 'type']],
            'listing_status'          => [[], ['Listing' => 'status']],
            'pdfTemplateType'         => [['pdfTemplateHtml', 'pdfTemplateODT', 'pdfTemplateCatalog'], ['PdfFeed' => 'type']],
            'budget_item_status'      => [[], ['BudgetItem' => 'status']],
        ];

        $dataDir = 'data/metadata/entityDefs';

        foreach ($map as $enumId => [$systemOptions, $entityFields]) {
            if (empty($entityFields)) {
                continue;
            }

            $allOptions = $this->getEnumOptions($enumId);
            $customOptions = array_values(array_diff($allOptions, $systemOptions));

            if (empty($customOptions)) {
                continue;
            }

            foreach ($entityFields as $entity => $field) {
                $file = $dataDir . '/' . $entity . '.json';

                $data = [];
                if (file_exists($file)) {
                    $data = json_decode(file_get_contents($file), true) ?? [];
                }

                $data['fields'][$field]['options'] = array_merge(['__APPEND__'], $customOptions);

                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }

    private function getEnumOptions(string $enumId): array
    {
        try {
            $stmt = $this->getPDO()->prepare(
                "
                SELECT eeo.id
                FROM extensible_enum_option eeo
                INNER JOIN extensible_enum_extensible_enum_option eeeeo
                    ON eeeeo.extensible_enum_option_id = eeo.id
                WHERE eeeeo.extensible_enum_id = :enumId
                  AND eeo.deleted = :false
                  AND eeeeo.deleted = :false
                ORDER BY eeeeo.sorting ASC, eeo.id ASC
            "
            );
            $stmt->execute([':enumId' => $enumId, ':false' => 0]);

            return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'id');
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
