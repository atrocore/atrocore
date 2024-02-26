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
            // ignore all
        }
    }


    protected function createIntermediateTable(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $tableName = "extensible_enum_extensible_enum_option";

        $toSchema->createTable($tableName);
        $this->addColumn($toSchema, $tableName, 'id', ['type' => 'varchar', 'len' => 24, 'notNull' => true]);
        $this->addColumn($toSchema, $tableName, 'deleted', ['type' => 'bool', 'default' => false]);
        $this->addColumn($toSchema, $tableName, 'created_at', ['type' => 'datetime', 'len' => 24, 'default' => null]);
        $this->addColumn($toSchema, $tableName, 'modified_at', ['type' => 'datetime', 'default' => null]);
        $this->addColumn($toSchema, $tableName, 'created_by_id', ['type' => 'varchar', 'len' => 24, 'default' => null]);
        $this->addColumn($toSchema, $tableName, 'modified_by_id', ['type' => 'varchar', 'len' => 24, 'default' => null]);
        $this->addColumn($toSchema, $tableName, 'sorting', ['type' => 'int', 'default' => null]);
        $this->addColumn($toSchema, $tableName, 'extensible_enum_option_id', ['type' => 'varchar', 'len' => 24, 'default' => null]);
        $this->addColumn($toSchema, $tableName, 'extensible_enum_id', ['type' => 'varchar', 'len' => 24, 'default' => null]);

        $toSchema->getTable($tableName)->setPrimaryKey(['id']);
        $toSchema->getTable($tableName)->addUniqueIndex(['deleted', 'extensible_enum_id', 'extensible_enum_option_id'], 'IDX_EXTENSIBLE_ENUM_OPTION_EXTENSIBLE_ENUM_UNIQUE_RELATION');
        $toSchema->getTable($tableName)->addIndex(['created_by_id', 'deleted'], 'IDX_EXTENSIBLE_ENUM_OPTION_EXTENSIBLE_ENUM_CREATED_BY_ID');
        $toSchema->getTable($tableName)->addIndex(['modified_by_id', 'deleted'], 'IDX_EXTENSIBLE_ENUM_OPTION_EXTENSIBLE_ENUM_MODIFIED_BY_ID');
        $toSchema->getTable($tableName)->addIndex(['extensible_enum_option_id', 'deleted']);
        $toSchema->getTable($tableName)->addIndex(['extensible_enum_id', 'deleted']);
        $toSchema->getTable($tableName)->addIndex(['created_at', 'deleted'], 'IDX_EXTENSIBLE_ENUM_OPTION_EXTENSIBLE_ENUM_CREATED_AT');
        $toSchema->getTable($tableName)->addIndex(['modified_at', 'deleted'], 'IDX_EXTENSIBLE_ENUM_OPTION_EXTENSIBLE_ENUM_MODIFIED_AT');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->execute($sql);
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
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $tableName = 'extensible_enum_option';
        $toSchema->getTable($tableName)->addUniqueIndex(['code', 'deleted']);

        if ($toSchema->getTable($tableName)->hasIndex('IDX_EXTENSIBLE_ENUM_OPTION_UNIQUE_OPTION')) {
            $toSchema->getTable($tableName)->dropIndex('IDX_EXTENSIBLE_ENUM_OPTION_UNIQUE_OPTION');
        }
        $this->dropColumn($toSchema, 'extensible_enum','extensible_enum_id');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->execute($sql);
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
