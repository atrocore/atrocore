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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\Util;

class V1Dot13Dot11 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-06 12:00:00');
    }

    public function up(): void
    {
        $vendorPath = 'vendor/atrocore';

        $scopes = [];

        foreach (scandir($vendorPath) as $module) {
            if (in_array($module, ['.', '..'])) {
                continue;
            }

            $scopesDir = $module === 'core' ? "$vendorPath/$module/app/Atro/Resources/metadata/scopes" : "$vendorPath/$module/app/Resources/metadata/scopes";
            if (!is_dir($scopesDir)) {
                continue;
            }

            foreach (scandir($scopesDir) as $scopeFile) {
                if (in_array($scopeFile, ['.', '..'])) {
                    continue;
                }

                $scopeDefs = json_decode(file_get_contents("$scopesDir/$scopeFile"), true);

                if (empty($scopeDefs['type'])) {
                    continue;
                }

                if (array_key_exists('stream', $scopeDefs) && !array_key_exists('streamDisabled', $scopeDefs)) {
                    $scopeDefs['streamDisabled'] = empty($scopeDefs['stream']);
                }

                if (!empty($scopeDefs['streamDisabled']) || !empty($scopeDefs['notStorable'])) {
                    continue;
                }

                $scopes[] = str_replace('.json', '', $scopeFile);
            }
        }

        $customPath = 'data/metadata/scopes';
        if (is_dir($customPath)) {
            foreach (scandir($customPath) as $scopeFile) {
                if (in_array($scopeFile, ['.', '..'])) {
                    continue;
                }

                $scopeDefs = json_decode(file_get_contents("$customPath/$scopeFile"), true);

                if (array_key_exists('stream', $scopeDefs) && !array_key_exists('streamDisabled', $scopeDefs)) {
                    $scopeDefs['streamDisabled'] = empty($scopeDefs['stream']);
                }

                if (!empty($scopeDefs['streamDisabled']) || !empty($scopeDefs['notStorable'])) {
                    continue;
                }

                $scopes[] = str_replace('.json', '', $scopeFile);
            }
        }

        $tables = [];
        foreach (array_unique($scopes) as $scope) {
            $tables[] = Util::toUnderScore(lcfirst($scope));
        }

        if ($this->isPgSQL()) {
            foreach ($tables as $table) {
                $uppercased = strtoupper($table);
                $this->exec("CREATE TABLE user_followed_{$table} (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, {$table}_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
                $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_{$uppercased}_UNIQUE_RELATION ON user_followed_{$table} (deleted, user_id, {$table}_id)");
                $this->exec("CREATE INDEX IDX_USER_FOLLOWED_{$uppercased}_CREATED_BY_ID ON user_followed_{$table} (created_by_id, deleted)");
                $this->exec("CREATE INDEX IDX_USER_FOLLOWED_{$uppercased}_MODIFIED_BY_ID ON user_followed_{$table} (modified_by_id, deleted)");
                $this->exec("CREATE INDEX IDX_USER_FOLLOWED_{$uppercased}_USER_ID ON user_followed_{$table} (user_id, deleted)");
                $this->exec("CREATE INDEX IDX_USER_FOLLOWED_{$uppercased}_{$uppercased}_ID ON user_followed_{$table} ({$table}_id, deleted)");
                $this->exec("CREATE INDEX IDX_USER_FOLLOWED_{$uppercased}_CREATED_AT ON user_followed_{$table} (created_at, deleted)");
                $this->exec("CREATE INDEX IDX_USER_FOLLOWED_{$uppercased}_MODIFIED_AT ON user_followed_{$table} (modified_at, deleted)");
            }
        } else {
            foreach ($tables as $table) {
                $uppercased = strtoupper($table);
                $this->exec("CREATE TABLE user_followed_{$table} (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, {$table}_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_FOLLOWED_{$uppercased}_UNIQUE_RELATION (deleted, user_id, {$table}_id), INDEX IDX_USER_FOLLOWED_{$uppercased}_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_USER_FOLLOWED_{$uppercased}_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_USER_FOLLOWED_{$uppercased}_USER_ID (user_id, deleted), INDEX IDX_USER_FOLLOWED_{$uppercased}_{$uppercased}_ID ({$table}_id, deleted), INDEX IDX_USER_FOLLOWED_{$uppercased}_CREATED_AT (created_at, deleted), INDEX IDX_USER_FOLLOWED_{$uppercased}_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            }
        }

        try {
            $records = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('user_followed_record')
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $records = [];
        }

        foreach ($records as $record) {
            $table = Util::toUnderScore(lcfirst($record['entity_type']));

            try {
                $this->getConnection()->createQueryBuilder()
                    ->insert("user_followed_{$table}")
                    ->setValue('id', ':id')
                    ->setValue('user_id', ':userId')
                    ->setValue("{$table}_id", ':entityId')
                    ->setParameter('id', $record['id'])
                    ->setParameter('userId', $record['user_id'])
                    ->setParameter('entityId', $record['entity_id'])
                    ->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
