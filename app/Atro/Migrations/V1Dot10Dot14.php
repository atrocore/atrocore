<?php

namespace Atro\Migrations;

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot10Dot14 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-05-15 15:00:00');
    }

    public function up(): void
    {
        $file = 'custom/Espo/Custom/Resources/metadata/entityDefs/File.json';

        if (file_exists($file)) {
            $metadata = json_decode(file_get_contents($file), true);

            $columns = $this->createCustomColumns($metadata);
            $this->importCustomFieldsValues($columns);

            $this->updateComposer('atrocore/core', '^1.10.14');
        }
    }

    protected function createCustomColumns(array $metadata): array
    {
        $result = [];

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('file') && $toSchema->hasTable('asset')) {
            $fileTable = $toSchema->getTable('file');
            $assetTable = $toSchema->getTable('asset');

            foreach ($metadata['fields'] as $field => $defs) {
                if (empty($defs['type']) || in_array($defs['type'], ['linkMultiple', 'script', 'file']) || !empty($defs['notStorable'])) {
                    continue;
                }

                $column = Util::toUnderScore(lcfirst($field));
                if ($defs['type'] == 'link') {
                    $column .= '_id';
                }

                if (!$assetTable->hasColumn($column)) {
                    continue;
                }

                if (!$fileTable->hasColumn($column)) {
                    $this->addColumn($toSchema, 'file', $column, ['type' => $defs['type'], 'default' => $defs['default'] ?? null]);

                    if (!empty($defs['index'])) {
                        $fileTable->addIndex([$column, 'deleted'], 'IDX_FILE_' . strtoupper($column));
                    }

                    if (!empty($defs['unique'])) {
                        $fileTable->addUniqueIndex([$column, 'deleted']);
                    }
                }

                $result[] = $column;
            }

            try {
                foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                    $this->getPDO()->exec($sql);
                }
            } catch (\Throwable $e) {
            }
        }

        return $result;
    }

    protected function importCustomFieldsValues(array $columns): void
    {
        if (empty($columns)) {
            return;
        }

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('file') && $toSchema->hasTable('asset')) {
            $connection = $this->getConnection();

            foreach ($columns as $column) {
                try {
                    $data = $connection
                        ->createQueryBuilder()
                        ->select('file_id, ' . $column)
                        ->from($connection->quoteIdentifier('asset'))
                        ->where('deleted = :false')
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->fetchAllAssociative();
                } catch (\Throwable $e) {
                    $data = [];
                }

                foreach ($data as $row) {
                    try {
                        $connection
                            ->createQueryBuilder()
                            ->update($connection->quoteIdentifier('file'))
                            ->set($column, ':value')
                            ->where('id = :id')
                            ->andWhere($column . ' IS NULL')
                            ->andWhere('deleted = :false')
                            ->setParameter('value', $row[$column], Mapper::getParameterType($row[$column]))
                            ->setParameter('id', $row['file_id'])
                            ->setParameter('false', false, ParameterType::BOOLEAN)
                            ->executeQuery();
                    } catch (\Throwable $e) {
                    }
                }
            }
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }
}
