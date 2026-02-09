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

        $dir = 'data/metadata/scopes';

        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (!in_array($item, ['.', '..'])) {
                    $parts = explode('.', $item);
                    $scope = $parts[0];

                    $content = @json_decode(file_get_contents($dir . '/' . $item), true);
                    if (!empty($content) && !empty($content['primaryEntityId'])) {
                        $tableName = Util::toUnderScore(lcfirst($scope));

                        $primaryEntityDefs = [];
                        $path = 'data/metadata/entityDefs/' . $content['primaryEntityId'] . '.json';
                        if (file_exists($path)) {
                            $primaryEntityDefs = @json_decode(file_get_contents($path), true);
                        }

                        if ($toSchema->hasTable($tableName)) {
                            $table = $toSchema->getTable($tableName);

                            foreach ($table->getColumns() as $column) {
                                $columnName = $column->getName();

                                if ($column->getType()->getName() === 'boolean' && $columnName !== 'deleted') {
                                    if (($primaryEntityDefs['fields'][Util::toCamelCase($columnName)]['notNull'] ?? null) === false) {
                                        continue;
                                    }

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
