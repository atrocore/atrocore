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
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V2Dot2Dot16 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-02-05 16:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        /** @var Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        foreach ($metadata->get('scopes') ?? [] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['primaryEntityId'])) {
                $tableName = Util::toUnderScore(lcfirst($scope));
                if ($toSchema->hasTable($tableName)) {
                    $table = $toSchema->getTable($tableName);
                    foreach ($metadata->get(['entityDefs', $scopeDefs['primaryEntityId'], 'fields']) ?? [] as $field => $fieldDefs) {
                        if (!empty($fieldDefs['type']) && $fieldDefs['type'] === 'bool' && !empty($fieldDefs['notNull'])) {
                            $columnName = Util::toUnderScore(lcfirst($field));
                            if ($table->hasColumn($columnName)) {
                                $column = $table->getColumn($columnName);
                                $column->setNotnull(true);
                                $column->setDefault(false);

                                try {
                                    $this->getConnection()->createQueryBuilder()
                                        ->update($this->getConnection()->quoteIdentifier($tableName))
                                        ->set($this->getConnection()->quoteIdentifier($columnName), ':false')
                                        ->where($this->getConnection()->quoteIdentifier($columnName) . ' IS NULL')
                                        ->setParameter('false', false, ParameterType::BOOLEAN)
                                        ->executeStatement();
                                } catch (\Exception $e) {
                                }
                            }
                        }
                    }
                }
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
