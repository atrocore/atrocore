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

class V2Dot1Dot17 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-26 08:00:00');
    }

    public function up(): void
    {
        /** @var Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');
        foreach ($metadata->get('scopes') ?? [] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['isHierarchyEntity'])) {
                $fromSchema = $this->getCurrentSchema();
                $toSchema = clone $fromSchema;
                $tableName = Util::toUnderScore($scope);
                if ($toSchema->hasTable($tableName)) {
                    $table = $toSchema->getTable($tableName);
                    $field = 'hierarchySortOrder';
                    if (!$table->hasColumn(util::toUnderScore($field))) {
                        $table->addColumn(Util::toUnderScore($field), 'integer', ['notnull' => false]);
                    }
                }
                foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                    $this->exec($sql);
                }
            }
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
