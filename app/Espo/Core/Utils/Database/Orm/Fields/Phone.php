<?php

namespace Espo\Core\Utils\Database\Orm\Fields;

class Phone extends Base
{
    protected function load($fieldName, $entityName)
    {
        return array(
            $entityName => array(
                'fields' => array(
                    $fieldName => array(
                        'select' => 'phoneNumbers.name',
                        'where' =>
                        array (
                            'LIKE' => \Espo\Core\Utils\Util::toUnderScore($entityName) . ".id IN (
                                SELECT entity_id
                                FROM entity_phone_number
                                JOIN phone_number ON phone_number.id = entity_phone_number.phone_number_id
                                WHERE
                                    entity_phone_number.deleted = 0 AND entity_phone_number.entity_type = '{$entityName}' AND
                                    phone_number.deleted = 0 AND phone_number.name LIKE {value}
                            )",
                            '=' => array(
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersMultiple']],
                                'sql' => 'phoneNumbersMultiple.name = {value}',
                                'distinct' => true
                            ),
                            '<>' => array(
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersMultiple']],
                                'sql' => 'phoneNumbersMultiple.name <> {value}',
                                'distinct' => true
                            ),
                            'IN' => array(
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersMultiple']],
                                'sql' => 'phoneNumbersMultiple.name IN {value}',
                                'distinct' => true
                            ),
                            'NOT IN' => array(
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersMultiple']],
                                'sql' => 'phoneNumbersMultiple.name NOT IN {value}',
                                'distinct' => true
                            ),
                            'IS NULL' => array(
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersMultiple']],
                                'sql' => 'phoneNumbersMultiple.name IS NULL',
                                'distinct' => true
                            ),
                            'IS NOT NULL' => array(
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersMultiple']],
                                'sql' => 'phoneNumbersMultiple.name IS NOT NULL',
                                'distinct' => true
                            )
                        ),
                        'orderBy' => 'phoneNumbers.name {direction}',
                    ),
                    $fieldName .'Data' => array(
                        'type' => 'text',
                        'notStorable' => true
                    ),
                    $fieldName . 'Numeric' => [
                        'type' => 'varchar',
                        'notStorable' => true,
                        'where' => [
                            'LIKE' => \Espo\Core\Utils\Util::toUnderScore($entityName) . ".id IN (
                                SELECT entity_id
                                FROM entity_phone_number
                                JOIN phone_number ON phone_number.id = entity_phone_number.phone_number_id
                                WHERE
                                    entity_phone_number.deleted = 0 AND entity_phone_number.entity_type = '{$entityName}' AND
                                    phone_number.deleted = 0 AND phone_number.numeric LIKE {value}
                            )",
                            '=' => [
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersNumericMultiple']],
                                'sql' => 'phoneNumbersNumericMultiple.numeric = {value}',
                                'distinct' => true
                            ],
                            '<>' => [
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersNumericMultiple']],
                                'sql' => 'phoneNumbersNumericMultiple.numeric <> {value}',
                                'distinct' => true
                            ],
                            'IN' => [
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersNumericMultiple']],
                                'sql' => 'phoneNumbersNumericMultiple.numeric IN {value}',
                                'distinct' => true
                            ],
                            'NOT IN' => [
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersNumericMultiple']],
                                'sql' => 'phoneNumbersNumericMultiple.numeric NOT IN {value}',
                                'distinct' => true
                            ],
                            'IS NULL' => [
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersNumericMultiple']],
                                'sql' => 'phoneNumbersNumericMultiple.numeric IS NULL',
                                'distinct' => true
                            ],
                            'IS NOT NULL' => [
                                'leftJoins' => [['phoneNumbers', 'phoneNumbersNumericMultiple']],
                                'sql' => 'phoneNumbersNumericMultiple.numeric IS NOT NULL',
                                'distinct' => true
                            ]
                        ]
                    ]
                ),
                'relations' => [
                    'phoneNumbers' => [
                        'type' => 'manyMany',
                        'entity' => 'PhoneNumber',
                        'relationName' => 'entityPhoneNumber',
                        'midKeys' => [
                            'entityId',
                            'phoneNumberId'
                        ],
                        'conditions' => [
                            'entityType' => $entityName
                        ],
                        'additionalColumns' => [
                            'entityType' => [
                                'type' => 'varchar',
                                'len' => 100
                            ],
                            'primary' => [
                                'type' => 'bool',
                                'default' => false
                            ]
                        ]
                    ]
                ]
            )
        );
    }
}
