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
use Ramsey\Uuid\Uuid;

class V3Dot0Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-30 12:00:00');
    }

    public function up(): void
    {
        $this->migrateConnection();
        $this->migrateStorage();
    }

    /**
     * Every connection type is a subtype of Connection now: the settings are moved from the "data" column to the
     * table of the subtype. The types of the modules are migrated by the migrations of the modules. The "data"
     * column is kept, the modules read it.
     */
    protected function migrateConnection(): void
    {
        $subtypes = $this->getConnectionSubtypes();

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($subtypes as $type => $columns) {
            $this->createSubtypeTable($toSchema, 'connection', $type, $columns);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        if (!$fromSchema->hasTable('connection') || !$fromSchema->getTable('connection')->hasColumn('data')) {
            return;
        }

        $records = $this->getDbal()->createQueryBuilder()
            ->select('id, type, data')
            ->from('connection')
            ->fetchAllAssociative();

        foreach ($records as $record) {
            if (empty($columns = $subtypes[$record['type']] ?? null)) {
                continue;
            }

            $data = @json_decode((string)$record['data'], true);

            $this->insertSubtypeRecord('connection', $record['type'], $record['id'], $columns, is_array($data) ? $data : []);
        }
    }

    /**
     * Every storage type is a subtype of Storage now: the settings of the local storage are moved from the columns
     * of the storage table to the table of the subtype. The types of the modules are migrated by the modules.
     */
    protected function migrateStorage(): void
    {
        $subtypes = [
            'local' => [
                'path'        => ['path', 'type' => 'string', 'length' => 255, 'default' => 'upload/files'],
                'syncFolders' => ['sync_folders', 'type' => 'boolean', 'notnull' => true, 'default' => false],
            ],
        ];

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($subtypes as $type => $columns) {
            $this->createSubtypeTable($toSchema, 'storage', $type, $columns);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        if (!$fromSchema->hasTable('storage')) {
            return;
        }

        $storageTable = $fromSchema->getTable('storage');
        foreach ($subtypes as $type => $columns) {
            $sourceColumns = [];
            foreach ($columns as $field => $options) {
                if ($storageTable->hasColumn($options[0])) {
                    $sourceColumns[$field] = $options[0];
                }
            }

            $records = $this->getDbal()->createQueryBuilder()
                ->select(implode(', ', array_merge(['id'], array_values($sourceColumns))))
                ->from('storage')
                ->where('type = :type')
                ->setParameter('type', $type)
                ->fetchAllAssociative();

            foreach ($records as $record) {
                $data = [];
                foreach ($sourceColumns as $field => $column) {
                    $data[$field] = $record[$column];
                }

                $this->insertSubtypeRecord('storage', $type, $record['id'], $columns, $data);
            }
        }
    }

    /**
     * The settings of the connection types: the field, its column and the column options.
     */
    protected function getConnectionSubtypes(): array
    {
        return [
            'atrocore' => [
                'atrocoreUrl' => ['atrocore_url', 'type' => 'string', 'length' => 255],
                'atrocoreToken' => ['atrocore_token', 'type' => 'string', 'length' => 2048],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                'httpBasicEnabled' => ['http_basic_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                'httpBasicUsername' => ['http_basic_username', 'type' => 'string', 'length' => 255],
                'httpBasicPassword' => ['http_basic_password', 'type' => 'string', 'length' => 255],
            ],
            'pdoSql' => [
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'dsn' => ['dsn', 'type' => 'string', 'length' => 255],
                'server' => ['server', 'type' => 'string', 'length' => 255],
            ],
            'mysql' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'dbName' => ['db_name', 'type' => 'string', 'length' => 255],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
            ],
            'psql' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'dbName' => ['db_name', 'type' => 'string', 'length' => 255],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
            ],
            'msql' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'dbName' => ['db_name', 'type' => 'string', 'length' => 255],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'additionalParameters' => ['additional_parameters', 'type' => 'text'],
            ],
            'ftp' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'ftpSsl' => ['ftp_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => false],
            ],
            'sftp' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
            ],
            'oauth2' => [
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'oauthGrantType' => ['oauth_grant_type', 'type' => 'string', 'length' => 255, 'default' => 'client_credentials'],
                'oauthUrl' => ['oauth_url', 'type' => 'string', 'length' => 255],
                'oauthClientId' => ['oauth_client_id', 'type' => 'string', 'length' => 255],
                'oauthClientSecret' => ['oauth_client_secret', 'type' => 'string', 'length' => 255],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                'httpBasicEnabled' => ['http_basic_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                'httpBasicUsername' => ['http_basic_username', 'type' => 'string', 'length' => 255],
                'httpBasicPassword' => ['http_basic_password', 'type' => 'string', 'length' => 255],
            ],
            'oauth1' => [
                'oauthConsumerKey' => ['oauth_consumer_key', 'type' => 'string', 'length' => 255],
                'oauthConsumerSecret' => ['oauth_consumer_secret', 'type' => 'string', 'length' => 255],
                'requestTokenUrl' => ['request_token_url', 'type' => 'string', 'length' => 255],
                'accessTokenUrl' => ['access_token_url', 'type' => 'string', 'length' => 255],
                'oauthVerifier' => ['oauth_verifier', 'type' => 'string', 'length' => 255],
                'oauthToken' => ['oauth_token', 'type' => 'string', 'length' => 2048],
                'oauthTokenSecret' => ['oauth_token_secret', 'type' => 'string', 'length' => 2048],
                'storeUrl' => ['store_url', 'type' => 'string', 'length' => 255],
                'apiTestUrl' => ['api_test_url', 'type' => 'string', 'length' => 255, 'default' => 'https://staging.davidsonsinc.com/rest/V1/products/011418204'],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
            ],
            'otto' => [
                'oauthGrantType' => ['oauth_grant_type', 'type' => 'string', 'length' => 255, 'default' => 'client_credentials'],
                'oauthUrl' => ['oauth_url', 'type' => 'string', 'length' => 255],
                'oauthClientId' => ['oauth_client_id', 'type' => 'string', 'length' => 255],
                'oauthClientSecret' => ['oauth_client_secret', 'type' => 'string', 'length' => 255],
                'oauthScope' => ['oauth_scope', 'type' => 'string', 'length' => 255, 'default' => 'partnerId installation developer'],
                'vendorOauthUrl' => ['vendor_oauth_url', 'type' => 'string', 'length' => 255],
                'vendorOauthScopes' => ['vendor_oauth_scopes', 'type' => 'jsonArray'],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
            ],
            'cookie' => [
                'payload' => ['payload', 'type' => 'text'],
                'loginUrl' => ['login_url', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                'httpBasicEnabled' => ['http_basic_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                'httpBasicUsername' => ['http_basic_username', 'type' => 'string', 'length' => 255],
                'httpBasicPassword' => ['http_basic_password', 'type' => 'string', 'length' => 255],
            ],
            'smtp' => [
                'smtpSecurity' => ['smtp_security', 'type' => 'string', 'length' => 255],
                'smtpServer' => ['smtp_server', 'type' => 'string', 'length' => 255],
                'smtpPassword' => ['smtp_password', 'type' => 'string', 'length' => 255],
                'smtpPort' => ['smtp_port', 'type' => 'integer'],
                'smtpUsername' => ['smtp_username', 'type' => 'string', 'length' => 255],
                'outboundEmailFromName' => ['outbound_email_from_name', 'type' => 'string', 'length' => 255],
                'outboundEmailFromAddress' => ['outbound_email_from_address', 'type' => 'string', 'length' => 255],
                'smtpAuthType' => ['smtp_auth_type', 'type' => 'string', 'length' => 255, 'default' => 'basic'],
                'smtpClientId' => ['smtp_client_id', 'type' => 'string', 'length' => 255],
                'smtpClientSecret' => ['smtp_client_secret', 'type' => 'string', 'length' => 255],
                'smtpOauthScope' => ['smtp_oauth_scope', 'type' => 'string', 'length' => 255],
                'smtpOauthAuthorizeUrl' => ['smtp_oauth_authorize_url', 'type' => 'string', 'length' => 255],
                'smtpOauthTokenUrl' => ['smtp_oauth_token_url', 'type' => 'string', 'length' => 255],
                'smtpAccessData' => ['smtp_access_data', 'type' => 'text'],
            ],
            'vertica' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'dbName' => ['db_name', 'type' => 'string', 'length' => 255],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
            ],
            'tokenAuthApi' => [
                'payload' => ['payload', 'type' => 'text'],
                'loginUrl' => ['login_url', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'headers' => ['headers', 'type' => 'text'],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                'httpBasicEnabled' => ['http_basic_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                'httpBasicUsername' => ['http_basic_username', 'type' => 'string', 'length' => 255],
                'httpBasicPassword' => ['http_basic_password', 'type' => 'string', 'length' => 255],
            ],
            'apiKey' => [
                'apiKeyName' => ['api_key_name', 'type' => 'string', 'length' => 255, 'default' => 'X-API-Key'],
                'apiKeyValue' => ['api_key_value', 'type' => 'string', 'length' => 2048],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                'httpBasicEnabled' => ['http_basic_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                'httpBasicUsername' => ['http_basic_username', 'type' => 'string', 'length' => 255],
                'httpBasicPassword' => ['http_basic_password', 'type' => 'string', 'length' => 255],
            ],
        ];
    }

    /**
     * The table of a subtype: its own id, the unique link to the supertype record that is removed together with
     * it, the columns of the subtype fields.
     */
    protected function createSubtypeTable(Schema $schema, string $supertypeTable, string $type, array $columns): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        if ($schema->hasTable($tableName)) {
            return;
        }

        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('supertype_id', 'string', ['length' => 36, 'notnull' => false]);

        foreach ($columns as $options) {
            $column = array_shift($options);
            $type = $options['type'];
            unset($options['type']);
            $table->addColumn($column, $type, array_merge(['notnull' => false], $options));
        }

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['supertype_id']);

        $foreignKeyName = $this->generateForeignKeyName($tableName . '_supertype_id');
        $table->addForeignKeyConstraint($supertypeTable, ['supertype_id'], ['id'], ['onDelete' => 'CASCADE'], $foreignKeyName);
        $table->addIndex(['supertype_id'], $foreignKeyName);
    }

    protected function insertSubtypeRecord(string $supertypeTable, string $type, string $supertypeId, array $columns, array $data): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        $conn = $this->getDbal();

        $exists = $conn->createQueryBuilder()
            ->select('id')
            ->from($tableName)
            ->where('supertype_id = :supertypeId')
            ->setParameter('supertypeId', $supertypeId)
            ->fetchOne();

        if ($exists !== false) {
            return;
        }

        $qb = $conn->createQueryBuilder()
            ->insert($tableName)
            ->setValue('id', ':id')
            ->setValue('supertype_id', ':supertypeId')
            ->setParameter('id', Uuid::uuid7()->toString())
            ->setParameter('supertypeId', $supertypeId);

        foreach ($columns as $field => $options) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            $value = $data[$field];
            $parameterType = ParameterType::STRING;

            switch ($options['type']) {
                case 'boolean':
                    $value = !empty($value);
                    $parameterType = ParameterType::BOOLEAN;
                    break;
                case 'integer':
                    if ($value === '') {
                        continue 2;
                    }
                    $value = (int)$value;
                    $parameterType = ParameterType::INTEGER;
                    break;
                case 'jsonArray':
                    $value = json_encode($value);
                    break;
                default:
                    $value = is_scalar($value) ? (string)$value : json_encode($value);
            }

            $qb->setValue($conn->quoteIdentifier($options[0]), ":$field");
            $qb->setParameter($field, $value, $parameterType);
        }

        $qb->executeStatement();
    }

    protected function getSubtypeTableName(string $supertypeTable, string $type): string
    {
        return $supertypeTable . '_type_' . strtolower(preg_replace('/([A-Z])/', '_$1', $type));
    }

    protected function generateForeignKeyName(string $name): string
    {
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        return 'FK_' . strtoupper($name);
    }
}
