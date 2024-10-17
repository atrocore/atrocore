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
use Atro\ORM\DB\RDB\Mapper;

class V1Dot11Dot20 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-10-16 17:00:00');
    }

    public function up(): void
    {
        $this->removeAllLayouts('Translation');
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

    protected function removeAllLayouts($entityName)
    {
        try {
            $ids = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('layout')
                ->where('entity = :entity')
                ->setParameter('entity', $entityName)
                ->fetchFirstColumn();

            if (empty($ids)) {
                return;
            }

            $sectionIds = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('layout_section')
                ->where('layout_id in (:layout_id)')
                ->setParameter('layout_id', $ids, Mapper::getParameterType($ids))
                ->fetchFirstColumn();

            $this->getConnection()->createQueryBuilder()
                ->delete('layout_row_item')
                ->where('section_id in (:section_id)')
                ->setparameter('section_id', $sectionIds, Mapper::getParameterType($sectionIds))
                ->executeStatement();

            foreach (['layout_side_panel_item', 'layout_section', 'layout_relationship_item', 'layout_list_item'] as $table) {
                $this->getConnection()->createQueryBuilder()
                    ->delete($table)
                    ->where('layout_id in (:layout_id)')
                    ->setparameter('layout_id', $ids, Mapper::getParameterType($ids))
                    ->executeStatement();
            }

            $this->getConnection()->createQueryBuilder()
                ->delete('layout')
                ->where('id in (:layout_id)')
                ->setparameter('layout_id', $ids, Mapper::getParameterType($ids))
                ->executeStatement();
        } catch (\Exception $e) {

        }
    }
}
