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

class V2Dot3Dot20 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-03 10:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP SEQUENCE IF EXISTS matching_number_seq");
            $this->exec("CREATE SEQUENCE matching_number_seq INCREMENT BY 1 MINVALUE 1 START 1");
            $this->exec("ALTER TABLE matching ADD COLUMN number INT DEFAULT nextval('matching_number_seq') NOT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_DC10F28996901F54 ON matching (number)");
        } else {
            $this->exec("ALTER TABLE matching ADD number INT AUTO_INCREMENT NOT NULL, ADD UNIQUE INDEX UNIQ_DC10F28996901F54 (number)");
        }

        $offset = 0;
        $limit = 100;

        while(true) {
            try {
                $matchings = $this->getDbal()->createQueryBuilder()
                    ->select('id, code, entity, master_entity, number')
                    ->from('matching')
                    ->orderBy('id', 'ASC')
                    ->setFirstResult($offset)
                    ->setMaxResults($limit)
                    ->fetchAllAssociative();
            } catch (\Throwable) {
                $matchings = [];
            }

            if (empty($matchings)) {
                break;
            }

            foreach ($matchings as $matching) {
                if (is_null($matching['number'])) {
                    continue;
                }

                $this->regenerateMatchedRecordHash($matching['id'], (int)$matching['number']);
            }

            $offset += $limit;
        }

        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX UNIQ_DC10F28977153098EB3B4E33");
        } else {
            $this->exec("DROP INDEX UNIQ_DC10F28977153098EB3B4E33 ON matching");
        }
        $this->exec("ALTER TABLE matching DROP COLUMN name");
        $this->exec("ALTER TABLE matching DROP COLUMN code");
    }

    protected function regenerateMatchedRecordHash(string $matchingId, int $number): void
    {
        $offset = 0;
        $limit = 100;

        while (true) {
            try {
                $rows = $this->getDbal()->createQueryBuilder()
                    ->select('id, source_entity, source_entity_id, master_entity, master_entity_id')
                    ->from('matched_record')
                    ->where('matching_id = :matchingId')
                    ->setParameter('matchingId', $matchingId)
                    ->orderBy('id', 'ASC')
                    ->setFirstResult($offset)
                    ->setMaxResults($limit)
                    ->fetchAllAssociative();
            } catch (\Throwable) {
                $rows = [];
            }

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $hash = md5(implode('_', [
                    $number,
                    $row['source_entity'],
                    $row['source_entity_id'],
                    $row['master_entity'],
                    $row['master_entity_id'],
                ]));

                $this->exec(
                    "UPDATE matched_record SET hash=" . $this->getPDO()->quote($hash) . " WHERE id=" . $this->getPDO()->quote($row['id'])
                );
            }

            $offset += $limit;
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable) {
        }
    }
}
