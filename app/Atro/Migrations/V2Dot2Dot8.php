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
use Doctrine\DBAL\ParameterType;

class V2Dot2Dot8 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-22 14:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE selection DROP name");
        $this->exec("ALTER TABLE selection ADD entity VARCHAR(255) DEFAULT NULL");

        $this->exec("ALTER TABLE selection_record rename TO selection_item");

        if ($this->isPgSQL()) {
            $this->exec("CREATE SEQUENCE selection_number_seq INCREMENT BY 1 MINVALUE 1 START 1");
            $this->exec("ALTER TABLE selection ADD number INT DEFAULT nextval('selection_number_seq') NOT NULL");

            $this->exec("ALTER INDEX idx_selection_record_unique RENAME TO IDX_SELECTION_ITEM_UNIQUE");
            $this->exec("ALTER INDEX idx_selection_record_selection_id RENAME TO IDX_SELECTION_ITEM_SELECTION_ID");
            $this->exec("ALTER INDEX idx_selection_record_entity_name RENAME TO IDX_SELECTION_ITEM_ENTITY_NAME");
            $this->exec("ALTER INDEX idx_selection_record_entity_id RENAME TO IDX_SELECTION_ITEM_ENTITY_ID");
        } else {
            $this->exec("ALTER TABLE selection ADD number INT AUTO_INCREMENT NOT NULL, ADD UNIQUE(number)");
            $this->exec("CREATE UNIQUE INDEX UNIQ_96A50CD796901F54 ON selection (number)");
            $this->exec("ALTER TABLE selection_item RENAME INDEX idx_selection_record_unique TO IDX_SELECTION_ITEM_UNIQUE");
            $this->exec("ALTER TABLE selection_item RENAME INDEX idx_selection_record_selection_id TO IDX_SELECTION_ITEM_SELECTION_ID");
            $this->exec("ALTER TABLE selection_item RENAME INDEX idx_selection_record_entity_name TO IDX_SELECTION_ITEM_ENTITY_NAME");
            $this->exec("ALTER TABLE selection_item RENAME INDEX idx_selection_record_entity_id TO IDX_SELECTION_ITEM_ENTITY_ID");
        }




        $limit = 10000;
        $offset = 0;
        while (true) {
            $selectionIds = array_column(
                $this->getConnection()->createQueryBuilder()
                    ->select('id')
                    ->from('selection')
                    ->where('type=:single and deleted=:false')
                    ->orderBy('id')
                    ->setMaxResults($limit)
                    ->setFirstResult($offset)
                    ->setParameter('single', 'single')
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchAllAssociative(),
                'id'
            );

            if (empty($selectionIds)) {
                break;
            }

            foreach ($selectionIds as $selectionId) {
                $result = $this->getConnection()->createQueryBuilder()
                    ->select('distinct sr.entity_type')
                    ->from('selection_item', 'sr')
                    ->join('sr', 'selection', 's', 'sr.selection_id = s.id')
                    ->where('s.id = :selectionId and sr.deleted = :false')
                    ->setParameter('selectionId', $selectionId)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchOne();

                if (!empty($result)) {
                    $this->getConnection()->createQueryBuilder()
                        ->update('selection')
                        ->set('entity', ':entity')
                        ->where('id = :id')
                        ->setParameter('id', $selectionId)
                        ->setParameter('entity', $result)
                        ->executeStatement();
                }
            }

            $offset += $limit;
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
