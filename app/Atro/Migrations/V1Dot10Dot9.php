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

class V1Dot10Dot9 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-05-02 00:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('sharing')) {
            $table = $toSchema->getTable('sharing');
            if (!$table->hasColumn('file_id')) {
                $table->addColumn('file_id', 'string', ['length' => 24]);
            }
            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->exec($sql);
            }
        }

        try {
            $this->getConnection()->createQueryBuilder()
                ->update('sharing')
                ->set('file_id', 'entity_id')
                ->where('deleted = :false')
                ->andWhere('entity_type = :file')
                ->setParameter('file', 'File')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
