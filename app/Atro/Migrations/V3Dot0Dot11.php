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
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;

class V3Dot0Dot11 extends Base
{
    public const TABLE = 'oauth_client_claim';

    /**
     * The former options of the token lifetimes in data/config.php: option => [column, default, min, max] in minutes.
     */
    protected const LIFETIME_OPTIONS = [
        'oauthAccessTokenTtl'  => ['access_token_lifetime', 60, 1, 1440],
        'oauthRefreshTokenTtl' => ['refresh_token_lifetime', 4320, 1, 525600],
    ];

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-20 16:00:00');
    }

    /**
     * The custom claims of the OAuth clients: a claim name is unique per client. The token lifetimes are settings of
     * each client instead of options in data/config.php: the existing clients get the lifetimes the options set.
     */
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->createTable($toSchema);
        $this->addLifetimeColumns($toSchema);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        $this->moveLifetimeOptions();
    }

    protected function addLifetimeColumns(Schema $schema): void
    {
        $table = $schema->getTable('oauth_client');

        foreach (self::LIFETIME_OPTIONS as [$column, $default]) {
            if (!$table->hasColumn($column)) {
                $table->addColumn($column, 'integer', ['notnull' => false, 'default' => $default]);
            }
        }
    }

    /**
     * The lifetime an option set (an ISO 8601 duration) goes to every client, within the limits of the field; the
     * options are removed, as is the option of the authorization code lifetime, which is fixed now.
     */
    protected function moveLifetimeOptions(): void
    {
        $config = $this->getConfig();

        foreach (self::LIFETIME_OPTIONS as $option => [$column, $default, $min, $max]) {
            $minutes = $default;
            if ($config->has($option)) {
                try {
                    $interval = new \DateInterval((string)$config->get($option));
                    $seconds = (new \DateTimeImmutable('@0'))->add($interval)->getTimestamp();
                    $minutes = max($min, min($max, (int)ceil($seconds / 60)));
                } catch (\Throwable $e) {
                    // an invalid duration keeps the default
                }
            }

            $this->getDbal()->createQueryBuilder()
                ->update('oauth_client')
                ->set($column, ':minutes')
                ->setParameter('minutes', $minutes, ParameterType::INTEGER)
                ->executeStatement();

            $config->remove($option);
        }

        $config->remove('oauthAuthCodeTtl');
        $config->save();
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
