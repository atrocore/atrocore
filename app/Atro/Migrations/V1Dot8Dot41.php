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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot8Dot41 extends Base
{
    public function up(): void
    {
        $this->createIntermediateTable();

        $this->setCodeAsNameOnNullOptions();

        $this->createNonDuplicatedIntermediateItems();

        $this->replaceAllDuplicatedOptionsByChosenOption();

        // There is no more duplication in extensible_enum_option, so we can delete
        $this->addUniqueIndexToCodeAndDropBelongToField();


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

            if($this->getCurrentSchema()->getTable('extensible_enum_option')->hasIndex('idx_extensible_enum_option_unique_option')){
                $this->execute("DROP INDEX idx_extensible_enum_option_unique_option ON extensible_enum_option;");
            }

            if($this->getCurrentSchema()->getTable('extensible_enum_option')->hasIndex('idx_extensible_enum_option_extensible_enum_id')){
                $this->execute("DROP INDEX idx_extensible_enum_option_unique_option ON extensible_enum_option;");
            }
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
           if($this->getCurrentSchema()->getTable('extensible_enum_option')->hasIndex('IDX_EXTENSIBLE_ENUM_OPTION_EXTENSIBLE_ENUM_ID')){
               $this->execute("DROP INDEX IDX_EXTENSIBLE_ENUM_OPTION_EXTENSIBLE_ENUM_ID ON extensible_enum_option;");
           }

           if($this->getCurrentSchema()->getTable('extensible_enum_option')->hasIndex('IDX_EXTENSIBLE_ENUM_OPTION_UNIQUE_OPTION')){
               $this->execute("DROP INDEX IDX_EXTENSIBLE_ENUM_OPTION_UNIQUE_OPTION ON extensible_enum_option;");
           }
       }
    }

    protected function createNonDuplicatedIntermediateItems(): void
    {
        $options = $this->getConnection()
            ->createQueryBuilder()
            ->from('extensible_enum_option')
            ->select("code, min(id) as id, min(extensible_enum_id) as extensible_enum_id")
            ->groupBy('code')
            ->having('count(*)=1')
            ->fetchAllAssociative();

        foreach ($options as $option) {
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
        }
    }

    protected function setCodeAsNameOnNullOptions(): void
    {
        $this->getConnection()
            ->createQueryBuilder()
            ->update('extensible_enum_option')
            ->set('code', 'name')
            ->where("code IS NULL")
            ->executeStatement();
    }


    protected function addUniqueIndexToCodeAndDropBelongToField(): void
    {

        if($this->isPgSQL()){
            $this->execute("CREATE UNIQUE INDEX UNIQ_6598AC4577153098EB3B4E33 ON extensible_enum_option (code, deleted);");
            $this->execute("ALTER TABLE extensible_enum_option DROP extensible_enum_id;");
        }else{
            $this->execute("ALTER TABLE extensible_enum_option DROP extensible_enum_id;");
            $this->execute("CREATE UNIQUE INDEX UNIQ_6598AC4577153098EB3B4E33 ON extensible_enum_option (code, deleted);");
        }

    }

    protected function replaceAllDuplicatedOptionsByChosenOption(): void
    {
          $duplicatedOptionsCode = $this->getConnection()
            ->createQueryBuilder()
            ->from('extensible_enum_option')
            ->select("code")
            ->groupBy('code')
            ->having('count(*)>1')
            ->fetchAllAssociative();

        foreach ($duplicatedOptionsCode as $duplicatedOptionCode) {

            $duplicatedOptionsForCode = $this->getConnection()
                ->createQueryBuilder()
                ->from('extensible_enum_option')
                ->select('id, extensible_enum_id')
                ->where('code=:code')
                ->setParameter('code', $duplicatedOptionCode['code'], Mapper::getParameterType($duplicatedOptionCode['code']))
                ->fetchAllAssociative();

            $chosenDuplicatedOption = $duplicatedOptionsForCode[0];

            array_shift($duplicatedOptionsForCode);

            foreach ($duplicatedOptionsForCode as $option) {
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
                    ->setParameter('sortOrder', $option['sort_order'], Mapper::getParameterType($option['sort_order']))
                    ->setParameter('extensibleEnumOptionId', $chosenDuplicatedOption['id'], Mapper::getParameterType($chosenDuplicatedOption['id']))
                    ->setParameter('createdById', 'system', ParameterType::STRING)
                    ->executeStatement();
            }

            // we update all the fields how has duplicated value and replace it by the chosen one
            /** @var \Espo\Core\Utils\Metadata $metadata */
            $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');
            foreach ($metadata->get('scope') as $scope => $scopeDefs) {
                $entityDefs = $metadata->get((['entityDefs', $scope]));
                foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                    if ($fieldDefs['type'] !== 'extensibleEnum'
                        && !in_array($fieldDefs['extensibleEnumId'], array_column($duplicatedOptionsForCode, 'extensible_enum_id'))
                    ) {
                        continue;
                    }
                    $this->getConnection()
                        ->createQueryBuilder()
                        ->update(Util::camelCaseToUnderscore($scope))
                        ->setValue(Util::camelCaseToUnderscore($field), ":value")
                        ->setParameter('value', $chosenDuplicatedOption['id'], Mapper::getParameterType($chosenDuplicatedOption['id']))
                        ->executeStatement();

                }
            }

            // remove dead List option on ProductAttributeValues
            if ($this->getCurrentSchema()->hasTable('product_attribute_value')) {

                $this->getConnection()
                    ->createQueryBuilder()
                    ->update('product_attribute_value')
                    ->set('varchar_value', ':chosenOptionId')
                    ->where('attribute_type = :attributeType')
                    ->andWhere('varchar_value IN (:duplicatedOptionsForCode)')
                    ->setParameter('attributeType', 'extensibleEnum', ParameterType::STRING)
                    ->setParameter('duplicatedOptionsForCode',
                        $duplicatedOptionsForCodeIds = array_column($duplicatedOptionsForCode, 'id'),
                        Mapper::getParameterType($duplicatedOptionsForCodeIds)
                    )
                    ->setParameter('chosenOptionId', $chosenDuplicatedOption['id'], Mapper::getParameterType($chosenDuplicatedOption['id']))
                    ->executeStatement();


                $query = "";
                foreach ($duplicatedOptionsForCode as $key => $value) {
                    if ($key > 0) {
                        $query .= " OR ";
                    }

                    $query .= "text_value LIKE :optionId$key";
                }
                $qb = $this->getConnection()
                    ->createQueryBuilder()
                    ->from('product_attribute_value')
                    ->select('id, text_value')
                    ->where('attribute_type = :attributeType')
                    ->andWhere($query)
                    ->setParameter('attributeType', 'extensibleMultiEnum', ParameterType::STRING)
                    ->setParameter('chosenOptionId', $chosenDuplicatedOption['id'],
                        Mapper::getParameterType($chosenDuplicatedOption['id'])
                    );

                foreach ($duplicatedOptionsForCode as $key => $value) {
                    $qb->setParameter("optionId$key", '%'.$value['id'].'%', Mapper::getParameterType($value['id']));
                }

                $pavs = $qb->fetchAllAssociative();

                foreach ($pavs as $pav) {
                    $value = array_diff(json_decode($pav['text_value'],true), $duplicatedOptionsForCodeIds);
                    $value[] = $chosenDuplicatedOption['id'];
                    $this->getConnection()
                        ->createQueryBuilder()
                        ->update('product_attribute_value')
                        ->set('text_value', ':optionIds')
                        ->where('id =:id')
                        ->setParameter('optionIds', json_encode($value), ParameterType::STRING)
                        ->setParameter('id', $pav['id'], Mapper::getParameterType($pav['id']))
                        ->executeStatement();
                }
            }

            // delete all the duplicated List option except the chosen one
            $this->getConnection()
                ->createQueryBuilder()
                ->delete('extensible_enum_option')
                ->where('code=:code AND id <> :selectedOptionId')
                ->setParameter('code', $duplicatedOptionCode['code'], Mapper::getParameterType($duplicatedOptionCode['code']))
                ->setParameter('selectedOptionId', $chosenDuplicatedOption['id'], Mapper::getParameterType($chosenDuplicatedOption['id']))
                ->executeStatement();

        }
    }

}
