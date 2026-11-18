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
use Doctrine\DBAL\Schema\Table;

class V3Dot0Dot10 extends Base
{
    public const TABLE = 'password_change_request';

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-17 12:00:00');
    }

    /**
     * The password change requests are kept in a service table that stores the hash of the token instead of the token.
     * The pending requests are dropped: they live for minutes and their tokens are not stored in the new form.
     */
    public function up(): void
    {
        $this->getDbal()->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE);

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
        $tokenHash = $table->addColumn('token_hash', 'string', ['length' => 64, 'notnull' => false]);
        if (!$this->isPgSQL()) {
            $tokenHash->setPlatformOption('collation', 'utf8mb4_bin');
        }
        $table->addColumn('user_id', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('expires_at', 'datetime', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['token_hash']);

        $this->addIndex($table, 'user_id');
        $this->addIndex($table, 'expires_at');
    }

    protected function addIndex(Table $table, string $column): void
    {
        $name = self::TABLE . '_' . $column;
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        $table->addIndex([$column], 'IDX_' . strtoupper($name));
    }
}
