<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\Migration;

use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\Database\Schema\Schema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema as DoctrineSchema;
use Doctrine\DBAL\Schema\Comparator;
use Espo\Services\App;
use Espo\Core\Utils\Config;

class Base
{
    private Schema $schema;
    private Connection $connection;
    private Config $config;
    private Comparator $comparator;

    public function __construct(\PDO $pdo, Config $config, ?Schema $schema)
    {
        $this->schema = $schema;
        $this->connection = $schema->getConnection();
        $this->config = $config;
        $this->comparator = new Comparator();
    }

    public function up(): void
    {
    }

    public function down(): void
    {
    }

    protected function getConnection(): Connection
    {
        return $this->connection;
    }

    protected function getConfig(): Config
    {
        return $this->config;
    }

    protected function getComparator(): Comparator
    {
        return $this->comparator;
    }

    protected function getCurrentSchema(): DoctrineSchema
    {
        return $this->schema->getCurrentSchema();
    }

    protected function getSchemaConverter(): Converter
    {
        return $this->schema->getSchemaConverter();
    }

    protected function addColumn(DoctrineSchema $schema, string $tableName, string $columnName, array $params): void
    {
        $this->getSchemaConverter()->addColumn($schema, $schema->getTable($tableName), $columnName, $this->schema->getOrmConverter()->convertField($params));
    }

    protected function dropColumn(DoctrineSchema $schema, string $tableName, string $columnName): void
    {
        $schema->getTable($tableName)->dropColumn($columnName);
    }

    protected function getPDO(): \PDO
    {
        return $this->getConnection()->getWrappedConnection()->getWrappedConnection();
    }

    protected function schemasDiffToSql(DoctrineSchema $fromSchema, DoctrineSchema $toSchema): array
    {
        return $this->getComparator()->compareSchemas($fromSchema, $toSchema)->toSql($this->getConnection()->getDatabasePlatform());
    }

    protected function isPgSQL(): bool
    {
        return $this->getSchemaConverter()::isPgSQL($this->getConnection());
    }

    protected function rebuild()
    {
        App::createRebuildNotification();
    }

    /**
     * @deprecated use rebuild instead
     */
    protected function rebuildByCronJob()
    {
        $this->rebuild();
    }

    protected function updateComposer(string $package, string $version): void
    {
        foreach (['composer.json', 'data/stable-composer.json'] as $filename) {
            if (!file_exists($filename)) {
                continue;
            }
            $data = json_decode(file_get_contents($filename), true);
            $data['require'] = array_merge($data['require'], [$package => $version]);
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}
