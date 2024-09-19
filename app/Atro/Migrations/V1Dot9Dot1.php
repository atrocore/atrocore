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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Utils\Util;

class V1Dot9Dot1 extends Base
{
    public function up(): void
    {
        $this->createIntermediateTable();

        $this->createIntermediateItems();

        $this->updateDuplicatedCode();

        $this->addUniqueIndexToCodeAndDropBelongToField();

        $this->updateComposer('atrocore/core', '^1.9.1');
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }

    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {

        }
    }


    protected function createIntermediateTable(): void
    {
        if($this->isPgSQL()){
            $this->execute("CREATE TABLE extensible_enum_extensible_enum_option (id VARCHAR(24) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sorting INT DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, extensible_enum_id VARCHAR(24) DEFAULT NULL, extensible_enum_option_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");
            $this->execute("CREATE UNIQUE INDEX IDX_EXTENSIBLE_ENUM_EXTENSIBLE_ENUM_OPTION_UNIQUE_RELATION ON extensible_enum_extensible_enum_option (deleted, extensible_enum_id, extensible_enum_option_id);");
            $this->execute("CREATE INDEX IDX_EXTENSIBLE_ENUM_EXTENSIBLE_ENUM_OPTION_CREATED_BY_ID ON extensible_enum_extensible_enum_option (created_by_id, deleted);");
            $this->execute("CREATE INDEX IDX_EXTENSIBLE_ENUM_EXTENSIBLE_ENUM_OPTION_MODIFIED_BY_ID ON extensible_enum_extensible_enum_option (modified_by_id, deleted);");
            $this->execute("CREATE INDEX IDX_50529C766F0CDC2EF0427B90BDB97B1C ON extensible_enum_extensible_enum_option (extensible_enum_id, deleted);");
            $this->execute("CREATE INDEX IDX_E67BAD4E2BB69A9BBC26754190C14C9D ON extensible_enum_extensible_enum_option (extensible_enum_option_id, deleted);");
            $this->execute("CREATE INDEX IDX_EXTENSIBLE_ENUM_EXTENSIBLE_ENUM_OPTION_CREATED_AT ON extensible_enum_extensible_enum_option (created_at, deleted);");
            $this->execute("CREATE INDEX IDX_EXTENSIBLE_ENUM_EXTENSIBLE_ENUM_OPTION_MODIFIED_AT ON extensible_enum_extensible_enum_option (modified_at, deleted);");

            $this->execute("DROP INDEX idx_extensible_enum_option_unique_option ON extensible_enum_option;");
            $this->execute("DROP INDEX idx_extensible_enum_option_unique_option ON extensible_enum_option;");

        }else{
            $this->execute("CREATE TABLE extensible_enum_extensible_enum_option 
                    (id VARCHAR(24) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, 
                    modified_at DATETIME DEFAULT NULL, sorting INT DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, 
                    modified_by_id VARCHAR(24) DEFAULT NULL, extensible_enum_id VARCHAR(24) DEFAULT NULL,
                     extensible_enum_option_id VARCHAR(24) DEFAULT NULL, 
                     UNIQUE INDEX IDX_EXTENSIBLE_ENUM_EXTENSIBLE_ENUM_OPTION_UNIQUE_RELATION 
                         (deleted, extensible_enum_id, extensible_enum_option_id), 
                     INDEX IDX_EXTENSIBLE_ENUM_EXTENSIBLE_ENUM_OPTION_CREATED_BY_ID (created_by_id, deleted), 
                     INDEX IDX_EXTENSIBLE_ENUM_EXTENSIBLE_ENUM_OPTION_MODIFIED_BY_ID (modified_by_id, deleted), 
                     INDEX IDX_50529C766F0CDC2EF0427B90BDB97B1C (extensible_enum_id, deleted), 
                     INDEX IDX_E67BAD4E2BB69A9BBC26754190C14C9D (extensible_enum_option_id, deleted), 
                     INDEX IDX_EXTENSIBLE_ENUM_EXTENSIBLE_ENUM_OPTION_CREATED_AT (created_at, deleted), 
                     INDEX IDX_EXTENSIBLE_ENUM_EXTENSIBLE_ENUM_OPTION_MODIFIED_AT (modified_at, deleted), 
                     PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;
            ");

            $this->execute("DROP INDEX IDX_EXTENSIBLE_ENUM_OPTION_EXTENSIBLE_ENUM_ID ON extensible_enum_option;");
            $this->execute("DROP INDEX IDX_EXTENSIBLE_ENUM_OPTION_UNIQUE_OPTION ON extensible_enum_option;");

        }
    }

    protected function createIntermediateItems(): void
    {
        $options = [true];
        $limit = 2000;
        $offset = 0;
        while (!empty($options)){

            $options = $this->getConnection()
                ->createQueryBuilder()
                ->from('extensible_enum_option')
                ->select("id, code, extensible_enum_id, sort_order")
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->fetchAllAssociative();

            foreach ($options as $option) {
                try {
                    $this->getConnection()
                        ->createQueryBuilder()
                        ->insert('extensible_enum_extensible_enum_option')
                        ->setValue('id', ':id')
                        ->setValue('sorting', ':sortOrder')
                        ->setValue('extensible_enum_id', ':extensibleEnumId')
                        ->setValue('extensible_enum_option_id', ':extensibleEnumOptionId')
                        ->setValue('created_by_id', ':createdById')
                        ->setValue('modified_by_id', ':createdById')
                        ->setParameter('id', Util::generateId())
                        ->setParameter('extensibleEnumId', $option['extensible_enum_id'], Mapper::getParameterType($option['extensible_enum_id']))
                        ->setParameter('extensibleEnumOptionId', $option['id'], Mapper::getParameterType($option['id']))
                        ->setParameter('createdById', 'system', ParameterType::STRING)
                        ->setParameter('sortOrder', $option['sort_order'], Mapper::getParameterType($option['sort_order']))
                        ->executeStatement();
                } catch (\Throwable $e) {
                }
            }

            $offset +=$limit;
        }

    }

    protected function addUniqueIndexToCodeAndDropBelongToField(): void
    {

        if($this->isPgSQL()){
            $this->execute("CREATE UNIQUE INDEX UNIQ_6598AC4577153098EB3B4E33 ON extensible_enum_option (code, deleted);");
            $this->execute("ALTER TABLE extensible_enum_option DROP extensible_enum_id;");
        }else{
            $this->execute("CREATE UNIQUE INDEX UNIQ_6598AC4577153098EB3B4E33 ON extensible_enum_option (code, deleted);");
            $this->execute("ALTER TABLE extensible_enum_option DROP extensible_enum_id;");
        }

    }

    protected function updateDuplicatedCode(): void
    {
        $duplicatedOptions = $this->getConnection()
            ->createQueryBuilder()
            ->from('extensible_enum_option')
            ->select("code")
            ->groupBy('code')
            ->having('count(*)>1')
            ->fetchAllAssociative();

        foreach ($duplicatedOptions as $duplicatedOption) {
            if($duplicatedOption['code'] === null) {
                continue;
            }

            $duplicatedOptionsForCode =  $this->getConnection()
                ->createQueryBuilder()
                ->from('extensible_enum_option')
                ->select("id")
                ->where('code=:code')
                ->setParameter('code', $duplicatedOption['code'], Mapper::getParameterType($duplicatedOption['code']))
                ->fetchAllAssociative();

            array_shift($duplicatedOptionsForCode);

            foreach ($duplicatedOptionsForCode as $key => $option) {
                $index = $key + 1;
                $code = $duplicatedOption['code'];

                if(empty($code)){
                    $code = "empty";
                }

                $newCode = $code. "-duplicate({$index})";

                $this->getConnection()
                    ->createQueryBuilder()
                    ->update('extensible_enum_option')
                    ->set('code', ':code')
                    ->where('id=:id')
                    ->setParameter('code', $newCode, Mapper::getParameterType($newCode))
                    ->setParameter('id', $option['id'], Mapper::getParameterType($option['id']))
                    ->executeStatement();
            }

        }
    }
}
