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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\Util;

/**
 * V2Dot6Dot4 and V2Dot6Dot6 added the gtin column and its index to the product table only. The entities that
 * are derivatives of Product have the same fields, so they get the column and the index too.
 */
class V2Dot6Dot7 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-26 12:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($this->getDerivativeEntities('Product') as $entityName) {
            $tableName = Util::toUnderScore(lcfirst($entityName));
            if (!$toSchema->hasTable($tableName)) {
                continue;
            }

            $table = $toSchema->getTable($tableName);

            if (!$table->hasColumn('gtin')) {
                $table->addColumn('gtin', 'string', ['length' => 255, 'notnull' => false]);
            }

            $indexName = Converter::generateIndexName($entityName, 'gtin');
            if (!$table->hasIndex($indexName)) {
                $table->addIndex(['gtin', 'deleted'], $indexName);
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    /**
     * @return string[]
     */
    protected function getDerivativeEntities(string $primaryEntity): array
    {
        $entities = [];

        $dir = 'data/metadata/scopes';
        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (in_array($item, ['.', '..'])) {
                    continue;
                }

                $content = @json_decode((string)file_get_contents($dir . '/' . $item), true);
                if (!empty($content['primaryEntityId']) && $content['primaryEntityId'] === $primaryEntity) {
                    $entities[] = explode('.', $item)[0];
                }
            }
        }

        return $entities;
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
