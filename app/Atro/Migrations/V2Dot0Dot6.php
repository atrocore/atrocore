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
use Doctrine\DBAL\ParameterType;

class V2Dot0Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-06-12 15:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE role_scope (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', has_access BOOLEAN DEFAULT 'false' NOT NULL, create_action VARCHAR(255) DEFAULT NULL, read_action VARCHAR(255) DEFAULT NULL, edit_action VARCHAR(255) DEFAULT NULL, delete_action VARCHAR(255) DEFAULT NULL, stream_action VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, role_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_ROLE_SCOPE_UNIQUE ON role_scope (deleted, name, role_id)");
            $this->exec("CREATE INDEX IDX_ROLE_SCOPE_ROLE_ID ON role_scope (role_id, deleted)");
            $this->exec("CREATE TABLE role_scope_field (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', read_action BOOLEAN DEFAULT 'false' NOT NULL, edit_action BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, role_scope_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_ROLE_SCOPE_FIELD_UNIQUE ON role_scope_field (deleted, name, role_scope_id)");
            $this->exec("CREATE INDEX IDX_ROLE_SCOPE_FIELD_ROLE_SCOPE_ID ON role_scope_field (role_scope_id, deleted)");
        } else {
            $this->exec("CREATE TABLE role_scope (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', has_access TINYINT(1) DEFAULT '0' NOT NULL, create_action VARCHAR(255) DEFAULT NULL, read_action VARCHAR(255) DEFAULT NULL, edit_action VARCHAR(255) DEFAULT NULL, delete_action VARCHAR(255) DEFAULT NULL, stream_action VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, role_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_ROLE_SCOPE_UNIQUE (deleted, name, role_id), INDEX IDX_ROLE_SCOPE_ROLE_ID (role_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE role_scope_field (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', read_action TINYINT(1) DEFAULT '0' NOT NULL, edit_action TINYINT(1) DEFAULT '0' NOT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, role_scope_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_ROLE_SCOPE_FIELD_UNIQUE (deleted, name, role_scope_id), INDEX IDX_ROLE_SCOPE_FIELD_ROLE_SCOPE_ID (role_scope_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }

        $roles = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($this->getConnection()->quoteIdentifier('role'))
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($roles as $role) {
            $data = @json_decode((string)$role['data'], true);
            if (!is_array($data)) {
                $data = [];
            }

            foreach ($data as $scope => $row) {
                $id = md5($role['id'] . '_' . $scope);

                $qb = $this->getConnection()->createQueryBuilder()
                    ->insert('role_scope')
                    ->setValue('id', ':id')
                    ->setValue('role_id', ':roleId')
                    ->setValue('name', ':scope')
                    ->setValue('has_access', ':hasAccess')
                    ->setValue('create_action', ':createAction')
                    ->setValue('read_action', ':readAction')
                    ->setValue('edit_action', ':editAction')
                    ->setValue('delete_action', ':deleteAction')
                    ->setValue('stream_action', ':streamAction')
                    ->setValue('created_at', ':date')
                    ->setValue('modified_at', ':date')
                    ->setValue('created_by_id', ':system')
                    ->setValue('modified_by_id', ':system')
                    ->setParameter('id', $id)
                    ->setParameter('roleId', $role['id'])
                    ->setParameter('scope', $scope)
                    ->setParameter('hasAccess', !empty($row), ParameterType::BOOLEAN)
                    ->setParameter('createAction', $row['create'] ?? null)
                    ->setParameter('readAction', $row['read'] ?? null)
                    ->setParameter('editAction', $row['edit'] ?? null)
                    ->setParameter('deleteAction', $row['delete'] ?? null)
                    ->setParameter('streamAction', $row['stream'] ?? null)
                    ->setParameter('date', date('Y-m-d H:i:s'))
                    ->setParameter('system', 'system');
                try {
                    $qb->executeQuery();
                } catch (\Throwable $e) {
                }
            }

            $fieldData = @json_decode((string)$role['field_data'], true);
            if (!is_array($fieldData)) {
                $fieldData = [];
            }

            foreach ($fieldData as $scope => $fields) {
                if (empty($fields)) {
                    continue;
                }

                foreach ($fields as $field => $row) {
                    $roleScopeId = md5($role['id'] . '_' . $scope);

                    $id = md5($roleScopeId . '_' . $field);

                    $qb = $this->getConnection()->createQueryBuilder()
                        ->insert('role_scope_field')
                        ->setValue('id', ':id')
                        ->setValue('role_scope_id', ':roleScopeId')
                        ->setValue('name', ':field')
                        ->setValue('read_action', ':readAction')
                        ->setValue('edit_action', ':editAction')
                        ->setValue('created_at', ':date')
                        ->setValue('modified_at', ':date')
                        ->setValue('created_by_id', ':system')
                        ->setValue('modified_by_id', ':system')
                        ->setParameter('id', $id)
                        ->setParameter('roleScopeId', $roleScopeId)
                        ->setParameter('field', $field)
                        ->setParameter('readAction', !empty($row['read']) && $row['read'] === 'yes',
                            ParameterType::BOOLEAN)
                        ->setParameter('editAction', !empty($row['edit']) && $row['edit'] === 'yes',
                            ParameterType::BOOLEAN)
                        ->setParameter('date', date('Y-m-d H:i:s'))
                        ->setParameter('system', 'system');
                    try {
                        $qb->executeQuery();
                    } catch (\Throwable $e) {
                    }
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
