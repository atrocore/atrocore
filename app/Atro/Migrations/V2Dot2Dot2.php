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

class V2Dot2Dot2 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-12 17:00:00');
    }

    public function up(): void
    {
        $this->exec('ALTER TABLE selection_record ADD selection_id VARCHAR(36) DEFAULT NULL');
        $this->exec('CREATE UNIQUE INDEX IDX_SELECTION_RECORD_UNIQUE ON selection_record (deleted, entity_type, entity_id, selection_id)');
        $this->exec('CREATE INDEX IDX_SELECTION_RECORD_SELECTION_ID ON selection_record (selection_id, deleted)');
        $this->exec('CREATE INDEX IDX_SELECTION_RECORD_ENTITY_NAME ON selection_record (entity_type, deleted)');
        $this->exec('CREATE INDEX IDX_SELECTION_RECORD_ENTITY_ID ON selection_record (entity_id, deleted)');

        $offset = 0;
        $limit = 2000;
        while(true) {
            $result = $this->getConnection()->createQueryBuilder()
                ->select('ssr.*')
                ->from('selection_selection_record', 'ssr')
                ->join('ssr', 'selection', 's', 's.id = ssr.selection_id')
                ->join('ssr', 'selection_record', 'sr', 'sr.id = ssr.selection_record_id')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            if(empty($result)) {
                break;
            }

            foreach ($result as $record) {
                try {
                    $this->getConnection()->createQueryBuilder()
                        ->update('selection_record')
                        ->set('selection_id', ':selectionId')
                        ->where('id = :id')
                        ->setParameter('id', $record['selection_record_id'])
                        ->setParameter('selectionId', $record['selection_id'])
                        ->executeStatement();
                }catch(\Exception $e) {

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
