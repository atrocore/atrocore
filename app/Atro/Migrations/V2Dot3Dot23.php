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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V2Dot3Dot23 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-17 10:00:00');
    }

    public function up(): void
    {
        foreach ($this->fetchDataPipelines() as $row) {
            $sourceEntity = (string)$row['source_entity_id'];
            $targetEntity = (string)$row['target_entity_id'];

            $table = Util::toUnderScore($sourceEntity);
            $tableName = $this->getDbal()->quoteIdentifier($table);
            $column = 'target_' . Util::toUnderScore($targetEntity) . '_id';

            if ($this->isPgSQL()) {
                $this->exec("ALTER TABLE $tableName RENAME COLUMN target_record_id TO $column");
            } else {
                $this->exec("ALTER TABLE $tableName CHANGE target_record_id $column VARCHAR(36) DEFAULT NULL");
            }

            $indexName = $this->generateUniqueIndexName($table, [$column, 'deleted']);
            $this->exec("CREATE UNIQUE INDEX $indexName ON $tableName ($column, deleted)");
        }
    }

    private function fetchDataPipelines(): array
    {
        try {
            return $this->getDbal()->createQueryBuilder()
                ->select('source_entity_id, target_entity_id')
                ->from('data_pipeline')
                ->where('deleted = :false')
                ->andWhere('source_entity_id IS NOT NULL')
                ->andWhere('target_entity_id IS NOT NULL')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function generateUniqueIndexName(string $tableName, array $columnNames): string
    {
        $hash = '';
        foreach (array_merge([$tableName], $columnNames) as $name) {
            $hash .= dechex(crc32($name));
        }

        return strtoupper(substr('uniq_' . $hash, 0, 63));
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}