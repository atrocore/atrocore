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

class V3Dot0Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-11 12:00:00');
    }

    /**
     * The OAuth 2.0 server: the table of the OauthClient entity, the tables of the issued tokens and the keys.
     */
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->createClientTable($toSchema);
        foreach (['oauth_access_token', 'oauth_auth_code'] as $tableName) {
            $this->createTokenTable($toSchema, $tableName, true);
        }
        $this->createTokenTable($toSchema, 'oauth_refresh_token', false);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        $this->createEncryptionKey();
        $this->createKeyPair();
    }

    protected function createClientTable(Schema $schema): void
    {
        if ($schema->hasTable('oauth_client')) {
            return;
        }

        $table = $schema->createTable('oauth_client');
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('deleted', 'boolean', ['notnull' => false, 'default' => false]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('is_active', 'boolean', ['notnull' => true, 'default' => true]);
        $table->addColumn('is_confidential', 'boolean', ['notnull' => true, 'default' => true]);
        $table->addColumn('client_credentials_grant', 'boolean', ['notnull' => true, 'default' => true]);
        $table->addColumn('authorization_code_grant', 'boolean', ['notnull' => true, 'default' => false]);
        $table->addColumn('refresh_token_grant', 'boolean', ['notnull' => true, 'default' => false]);
        $table->addColumn('redirect_uris', 'jsonArray', ['notnull' => false]);
        $table->addColumn('secret_hash', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->addColumn('modified_at', 'datetime', ['notnull' => false]);
        $table->addColumn('user_id', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('created_by_id', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('modified_by_id', 'string', ['length' => 36, 'notnull' => false]);
        $table->setPrimaryKey(['id']);

        foreach (['user_id', 'created_by_id', 'modified_by_id'] as $column) {
            $table->addIndex([$column, 'deleted'], $this->generateIndexName('oauth_client', $column));
        }
    }

    protected function createTokenTable(Schema $schema, string $tableName, bool $isIssuedToUser): void
    {
        if ($schema->hasTable($tableName)) {
            return;
        }

        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('identifier', 'string', ['length' => 100, 'notnull' => false]);

        if ($isIssuedToUser) {
            $table->addColumn('client_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('user_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('scopes', 'jsonArray', ['notnull' => false]);
        } else {
            $table->addColumn('access_token_id', 'string', ['length' => 36, 'notnull' => false]);
        }

        $table->addColumn('expires_at', 'datetime', ['notnull' => false]);
        $table->addColumn('is_revoked', 'boolean', ['notnull' => true, 'default' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['identifier']);

        if ($isIssuedToUser) {
            $this->addIndex($table, $tableName, 'client_id');
            $this->addIndex($table, $tableName, 'user_id');
            $this->addCascadeForeignKey($table, $tableName, 'client_id', 'oauth_client');
        } else {
            $this->addIndex($table, $tableName, 'access_token_id');
            $this->addCascadeForeignKey($table, $tableName, 'access_token_id', 'oauth_access_token');
        }

        $this->addIndex($table, $tableName, 'expires_at');
    }

    /**
     * The tokens are deleted together with the record they belong to.
     */
    protected function addCascadeForeignKey(Table $table, string $tableName, string $column, string $foreignTableName): void
    {
        $name = $tableName . '_' . $column;
        if (strlen($name) > 55) {
            $name = md5($name);
        }
        $name = 'FK_' . strtoupper($name);

        $table->addForeignKeyConstraint($foreignTableName, [$column], ['id'], ['onDelete' => 'CASCADE'], $name);
        $table->addIndex([$column], $name);
    }

    protected function addIndex(Table $table, string $tableName, string $column): void
    {
        $table->addIndex([$column], $this->generateIndexName($tableName, $column));
    }

    protected function generateIndexName(string $tableName, string $column): string
    {
        $name = $tableName . '_' . $column;
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        return 'IDX_' . strtoupper($name);
    }

    /**
     * The key that encrypts the refresh tokens and the authorization codes.
     */
    protected function createEncryptionKey(): void
    {
        if (!empty($this->getConfig()->get('oauthEncryptionKey'))) {
            return;
        }

        $this->getConfig()->set('oauthEncryptionKey', base64_encode(random_bytes(32)));
        $this->getConfig()->save();
    }

    /**
     * The RSA key pair that signs the access tokens.
     */
    protected function createKeyPair(): void
    {
        $dir = 'data/keys';
        $privateKeyPath = $dir . '/oauth-private.key';
        $publicKeyPath = $dir . '/oauth-public.key';

        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            return;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false || !openssl_pkey_export($resource, $privateKey)) {
            throw new \Error('The OAuth key pair cannot be created: ' . openssl_error_string());
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($privateKeyPath, $privateKey);
        chmod($privateKeyPath, 0600);

        file_put_contents($publicKeyPath, openssl_pkey_get_details($resource)['key']);
        chmod($publicKeyPath, 0600);
    }
}
