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
use Doctrine\DBAL\Schema\Schema;

class V3Dot0Dot11 extends Base
{
    public const TABLE = 'oauth_client_claim';

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-20 16:00:00');
    }

    /**
     * The custom claims of the OAuth clients: a claim name is unique per client.
     */
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->createTable($toSchema);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }
    }

    protected function createTable(Schema $schema): void
    {
        if ($schema->hasTable(self::TABLE)) {
            return;
        }

        $table = $schema->createTable(self::TABLE);
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('deleted', 'boolean', ['notnull' => false, 'default' => false]);
        $table->addColumn('value', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->addColumn('modified_at', 'datetime', ['notnull' => false]);
        $table->addColumn('oauth_client_id', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('created_by_id', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('modified_by_id', 'string', ['length' => 36, 'notnull' => false]);
        $table->setPrimaryKey(['id']);

        $table->addUniqueIndex(['deleted', 'name', 'oauth_client_id'], $this->generateIndexName('unique'));

        foreach (['oauth_client_id', 'created_by_id', 'modified_by_id'] as $column) {
            $table->addIndex([$column, 'deleted'], $this->generateIndexName($column));
        }
    }

    protected function generateIndexName(string $name): string
    {
        $res = self::TABLE . '_' . $name;
        if (strlen($res) > 55) {
            $res = md5($res);
        }

        return 'IDX_' . strtoupper($res);
    }
}
