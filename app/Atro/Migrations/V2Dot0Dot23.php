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
use Atro\Core\Utils\IdGenerator;
use Atro\ORM\DB\RDB\Mapper;
use Atro\Core\Utils\Util;

class V2Dot0Dot23 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-07-30 18:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE layout_list_item ADD attribute_id VARCHAR(255) DEFAULT NULL");

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

        /* Create list options */
        $options = [
            [
                "id"                => "physical_goods",
                "extensibleEnumId"  => "product_group_item_type",
                "name"              => "Physical goods",
                "code"              => "physical_goods",
                "sortOrder"         => 0
            ],
            [
                "id"                => "services",
                "extensibleEnumId"  => "product_group_item_type",
                "name"              => "Services",
                "code"              => "services",
                "sortOrder"         => 10
            ],
            [
                "id"                => "digital_products",
                "extensibleEnumId"  => "product_group_item_type",
                "name"              => "Digital products",
                "code"              => "digital_products",
                "sortOrder"         => 20
            ],
            [
                "id"                => "legal_rights",
                "extensibleEnumId"  => "product_group_item_type",
                "name"              => "Legal rights",
                "code"              => "legal_rights",
                "sortOrder"         => 30
            ]
        ];

        $this->getConnection()->createQueryBuilder()
            ->delete('extensible_enum_extensible_enum_option')
            ->where('extensible_enum_id = :id')
            ->setParameter('id', 'product_group_item_type')
            ->executeStatement();

        $this->getConnection()->createQueryBuilder()
            ->delete('extensible_enum_option')
            ->where('id IN (:ids)')
            ->setParameter('ids', array_column($options, 'id'), Mapper::getParameterType(array_column($options, 'id')))
            ->executeStatement();

        foreach ($options as $option) {
            $qb = $this->getConnection()->createQueryBuilder()
                ->insert('extensible_enum_option')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('code', ':code')
                ->setValue('sort_order', ':sortOrder')
                ->setParameters($option);

            $qb2 = $this->getConnection()->createQueryBuilder()
                ->insert('extensible_enum_extensible_enum_option')
                ->setValue('id', ':id')
                ->setValue('extensible_enum_id', ':enumId')
                ->setValue('extensible_enum_option_id', ':optionId')
                ->setParameter('id', IdGenerator::uuid())
                ->setParameter('enumId', $option['extensibleEnumId'])
                ->setParameter('optionId', $option['id']);

            try {
                $qb->executeQuery();
                $qb2->executeQuery();
            } catch (\Throwable $e) {
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