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
use Espo\Core\Utils\Util;

class V1Dot10Dot64 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-08-26 15:00:00');
    }

    public function up(): void
    {

        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE layout (id VARCHAR(24) NOT NULL, deleted BOOLEAN DEFAULT 'false', entity VARCHAR(255) DEFAULT NULL, view_type VARCHAR(255) DEFAULT NULL, preferences_id VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, layout_profile_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");

            $this->exec("CREATE INDEX IDX_LAYOUT_CREATED_BY_ID ON layout (created_by_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_MODIFIED_BY_ID ON layout (modified_by_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_LAYOUT_PROFILE_ID ON layout (layout_profile_id, deleted);");

            $this->exec("CREATE TABLE layout_list_item (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', sort_order INT DEFAULT NULL, link BOOLEAN DEFAULT 'false' NOT NULL, not_sortable BOOLEAN DEFAULT 'false' NOT NULL, align VARCHAR(255) DEFAULT NULL, width DOUBLE PRECISION DEFAULT NULL, width_px DOUBLE PRECISION DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, layout_id VARCHAR(24) DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");

            $this->exec("CREATE INDEX IDX_LAYOUT_LIST_ITEM_LAYOUT_ID ON layout_list_item (layout_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_LIST_ITEM_CREATED_BY_ID ON layout_list_item (created_by_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_LIST_ITEM_MODIFIED_BY_ID ON layout_list_item (modified_by_id, deleted);");

            $this->exec("CREATE TABLE layout_profile (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', description TEXT DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");

            $this->exec("CREATE INDEX IDX_LAYOUT_PROFILE_CREATED_BY_ID ON layout_profile (created_by_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_PROFILE_MODIFIED_BY_ID ON layout_profile (modified_by_id, deleted);");

            $this->exec("CREATE TABLE layout_relationship_item (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', sort_order INT DEFAULT NULL, style VARCHAR(255) DEFAULT NULL, hidden_per_default BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, layout_id VARCHAR(24) DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");

            $this->exec("CREATE INDEX IDX_LAYOUT_RELATIONSHIP_ITEM_LAYOUT_ID ON layout_relationship_item (layout_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_RELATIONSHIP_ITEM_CREATED_BY_ID ON layout_relationship_item (created_by_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_RELATIONSHIP_ITEM_MODIFIED_BY_ID ON layout_relationship_item (modified_by_id, deleted);");

            $this->exec("CREATE TABLE layout_row_item (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', column_index INT DEFAULT NULL, row_index INT DEFAULT NULL, full_width BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, section_id VARCHAR(24) DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");

            $this->exec("CREATE INDEX IDX_LAYOUT_ROW_ITEM_SECTION_ID ON layout_row_item (section_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_ROW_ITEM_CREATED_BY_ID ON layout_row_item (created_by_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_ROW_ITEM_MODIFIED_BY_ID ON layout_row_item (modified_by_id, deleted);");

            $this->exec("CREATE TABLE layout_section (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', style VARCHAR(255) DEFAULT NULL, dynamic_logic_visible TEXT DEFAULT NULL, sort_order INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, layout_id VARCHAR(24) DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");

            $this->exec("CREATE INDEX IDX_LAYOUT_SECTION_LAYOUT_ID ON layout_section (layout_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_SECTION_CREATED_BY_ID ON layout_section (created_by_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_SECTION_MODIFIED_BY_ID ON layout_section (modified_by_id, deleted);");

            $this->exec("COMMENT ON COLUMN layout_section.dynamic_logic_visible IS '(DC2Type:jsonObject)';");

            $this->exec("CREATE TABLE layout_side_panel_item (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', sort_order INT DEFAULT NULL, style VARCHAR(255) DEFAULT NULL, sticked BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, layout_id VARCHAR(24) DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");

            $this->exec("CREATE INDEX IDX_LAYOUT_SIDE_PANEL_ITEM_LAYOUT_ID ON layout_side_panel_item (layout_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_SIDE_PANEL_ITEM_CREATED_BY_ID ON layout_side_panel_item (created_by_id, deleted);");

            $this->exec("CREATE INDEX IDX_LAYOUT_SIDE_PANEL_ITEM_MODIFIED_BY_ID ON layout_side_panel_item (modified_by_id, deleted);");
        }else{

        }

        $types = ['list', 'listSmall', 'detail', 'detailSmall', 'relationships', 'sidePanelsDetail', 'sidePanelsEdit', 'sidePanelsDetailSmall', 'sidePanelsEditSmall'];
        // Migrate layout from custom to database
        if (is_dir("data/layouts")) {
            foreach (scandir("data/layouts") as $dir) {
                if (in_array($dir, ['.', '..'])) {
                    continue;
                }

                foreach ($types as $type) {
                    $file = "data/layouts/$dir/$type.json";
                    if (file_exists($file)) {
                        $content = @json_decode(file_get_contents($file), true);
                        if (!empty($content)) {
                            $id = Util::generateId();
                            $this->getConnection()->createQueryBuilder()
                                ->insert('layout')
                                ->values([
                                    'id'     => ':id',
                                    'entity' => ':entity',
                                    'type'   => ':type'
                                ])
                                ->setParameter('id', $id)
                                ->setParameter('entity', $dir)
                                ->setParameter('type', $type)
                                ->executeStatement();

                            $functionName = "create" . ucfirst($type) . "LayoutContent";
                            if (method_exists($this, $functionName)) {
                                $this->$functionName($id, $content);
                            }

                        }
                    }
                }

            }
        }
    }

    public function createListLayoutContent($layoutId, $data)
    {
        foreach ($data as $index => $item) {
            try {
                $qb = $this->getConnection()->createQueryBuilder()
                    ->insert('layout_list_item')
                    ->values([
                        'id'         => ':id',
                        'name'       => ':name',
                        'sort_order' => ':sortOrder',
                        'layout_id'  => ':layoutId',
                    ]);

                if (!empty($item['link'])) {
                    $qb->setValue('link', ':link');
                }
                if (!empty($item['align'])) {
                    $qb->setValue('align', ':align');
                }
                if (!empty($item['width'])) {
                    $qb->setValue('width', ':width');
                }
                if (!empty($item['widthPx'])) {
                    $qb->setValue('width_px', ':widthPx');
                }


                $qb->setParameters(array_merge($item, ['id' => Util::generateId(), 'sortOrder' => $index, 'layoutId' => $layoutId]))
                    ->executeStatement();
            } catch (\Throwable $e) {
            }

        }
    }

    public function createListSmallLayoutContent($layoutId, $data)
    {
        $this->createListLayoutContent($layoutId, $data);
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
