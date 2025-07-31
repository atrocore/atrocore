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

class V2Dot0Dot22 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-07-30 18:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        /* Create AccountGroup and related fields and tables */

        if (!$toSchema->hasTable('account_group')) {
            $table = $toSchema->createTable('account_group');

            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
            $table->addColumn('description', 'text', ['default' => null, 'notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['default' => null, 'notnull' => false]);
            $table->addColumn('modified_at', 'datetime', ['default' => null, 'notnull' => false]);
            $table->addColumn('code', 'string', ['length' => 255, 'default' => null, 'notnull' => false]);
            $table->addColumn('discount_percentage', 'float', ['default' => null, 'notnull' => false]);
            $table->addColumn('created_by_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);

            $table->addUniqueIndex(['code', 'deleted'], 'UNIQ_5B4F9DB477153098EB3B4E33');
            $table->addIndex(['code', 'deleted'], 'IDX_ACCOUNT_GROUP_CODE');
            $table->addIndex(['created_by_id', 'deleted'], 'IDX_ACCOUNT_GROUP_CREATED_BY_ID');
            $table->addIndex(['modified_by_id', 'deleted'], 'IDX_ACCOUNT_GROUP_MODIFIED_BY_ID');
            $table->addIndex(['name', 'deleted'], 'IDX_ACCOUNT_GROUP_NAME');
            $table->addIndex(['created_at', 'deleted'], 'IDX_ACCOUNT_GROUP_CREATED_AT');
            $table->addIndex(['modified_at', 'deleted'], 'IDX_ACCOUNT_GROUP_MODIFIED_AT');

            $table->setPrimaryKey(['id']);
        }

        if (!$toSchema->hasTable('user_followed_account_group')) {
            $table = $toSchema->createTable('user_followed_account_group');

            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['default' => null, 'notnull' => false]);
            $table->addColumn('modified_at', 'datetime', ['default' => null, 'notnull' => false]);
            $table->addColumn('created_by_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);
            $table->addColumn('account_group_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);
            $table->addColumn('user_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);

            $table->addUniqueIndex(['deleted', 'account_group_id', 'user_id'], 'IDX_USER_FOLLOWED_ACCOUNT_GROUP_UNIQUE_RELATION');
            $table->addIndex(['created_by_id', 'deleted'], 'IDX_USER_FOLLOWED_ACCOUNT_GROUP_CREATED_BY_ID');
            $table->addIndex(['modified_by_id', 'deleted'], 'IDX_USER_FOLLOWED_ACCOUNT_GROUP_MODIFIED_BY_ID');
            $table->addIndex(['account_group_id', 'deleted'], 'IDX_USER_FOLLOWED_ACCOUNT_GROUP_ACCOUNT_GROUP_ID');
            $table->addIndex(['user_id', 'deleted'], 'IDX_USER_FOLLOWED_ACCOUNT_GROUP_USER_ID');
            $table->addIndex(['created_at', 'deleted'], 'IDX_USER_FOLLOWED_ACCOUNT_GROUP_CREATED_AT');
            $table->addIndex(['modified_at', 'deleted'], 'IDX_USER_FOLLOWED_ACCOUNT_GROUP_MODIFIED_AT');

            $table->setPrimaryKey(['id']);
        }

        if ($toSchema->hasTable('account')) {
            $table = $toSchema->getTable('account');

            if (!$table->hasColumn('account_group_id')) {
                $table->addColumn('account_group_id', 'string', ['length' => 36, 'notnull' => false]);

                $table->addIndex(['account_group_id', 'deleted'], 'IDX_ACCOUNT_ACCOUNT_GROUP_ID');
            }
        }

        /* Create ProductGroup and related fields and tables */

        if (!$toSchema->hasTable('product_group')) {
            $table = $toSchema->createTable('product_group');

            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
            $table->addColumn('description', 'text', ['default' => null, 'notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['default' => null, 'notnull' => false]);
            $table->addColumn('modified_at', 'datetime', ['default' => null, 'notnull' => false]);
            $table->addColumn('code', 'string', ['length' => 255, 'default' => null, 'notnull' => false]);
            $table->addColumn('item_type', 'string', ['length' => 255, 'default' => null, 'notnull' => false]);
            $table->addColumn('created_by_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);

            $table->addUniqueIndex(['code', 'deleted'], 'UNIQ_CC9C3F9977153098EB3B4E33');
            $table->addIndex(['code', 'deleted'], 'IDX_PRODUCT_GROUP_CODE');
            $table->addIndex(['created_by_id', 'deleted'], 'IDX_PRODUCT_GROUP_CREATED_BY_ID');
            $table->addIndex(['modified_by_id', 'deleted'], 'IDX_PRODUCT_GROUP_MODIFIED_BY_ID');
            $table->addIndex(['name', 'deleted'], 'IDX_PRODUCT_GROUP_NAME');
            $table->addIndex(['created_at', 'deleted'], 'IDX_PRODUCT_GROUP_CREATED_AT');
            $table->addIndex(['modified_at', 'deleted'], 'IDX_PRODUCT_GROUP_MODIFIED_AT');

            $table->setPrimaryKey(['id']);
        }

        if (!$toSchema->hasTable('user_followed_product_group')) {
            $table = $toSchema->createTable('user_followed_product_group');

            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['default' => null, 'notnull' => false]);
            $table->addColumn('modified_at', 'datetime', ['default' => null, 'notnull' => false]);
            $table->addColumn('created_by_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);
            $table->addColumn('product_group_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);
            $table->addColumn('user_id', 'string', ['length' => 36, 'default' => null, 'notnull' => false]);

            $table->addUniqueIndex(['deleted', 'product_group_id', 'user_id'], 'IDX_USER_FOLLOWED_PRODUCT_GROUP_UNIQUE_RELATION');
            $table->addIndex(['created_by_id', 'deleted'], 'IDX_USER_FOLLOWED_PRODUCT_GROUP_CREATED_BY_ID');
            $table->addIndex(['modified_by_id', 'deleted'], 'IDX_USER_FOLLOWED_PRODUCT_GROUP_MODIFIED_BY_ID');
            $table->addIndex(['product_group_id', 'deleted'], 'IDX_USER_FOLLOWED_PRODUCT_GROUP_PRODUCT_GROUP_ID');
            $table->addIndex(['user_id', 'deleted'], 'IDX_USER_FOLLOWED_PRODUCT_GROUP_USER_ID');
            $table->addIndex(['created_at', 'deleted'], 'IDX_USER_FOLLOWED_PRODUCT_GROUP_CREATED_AT');
            $table->addIndex(['modified_at', 'deleted'], 'IDX_USER_FOLLOWED_PRODUCT_GROUP_MODIFIED_AT');

            $table->setPrimaryKey(['id']);
        }

        if ($toSchema->hasTable('product')) {
            $table = $toSchema->getTable('product');

            if (!$table->hasColumn('product_group_id')) {
                $table->addColumn('product_group_id', 'string', ['length' => 36, 'notnull' => false]);

                $table->addIndex(['product_group_id', 'deleted'], 'IDX_PRODUCT_PRODUCT_GROUP_ID');
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
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