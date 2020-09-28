<?php

namespace Espo\Core\Utils\Database\Orm\Fields;

class Email extends Base
{
    protected function load($fieldName, $entityName)
    {
        return array(
            $entityName => array(
                'fields' => array(
                    $fieldName => array(
                        'select' => 'emailAddresses.name',
                        'where' =>
                        array (
                            'LIKE' => \Espo\Core\Utils\Util::toUnderScore($entityName) . ".id IN (
                                SELECT entity_id
                                FROM entity_email_address
                                JOIN email_address ON email_address.id = entity_email_address.email_address_id
                                WHERE
                                    entity_email_address.deleted = 0 AND entity_email_address.entity_type = '{$entityName}' AND
                                    email_address.deleted = 0 AND email_address.lower LIKE {value}
                            )",
                            '=' => array(
                                'leftJoins' => [['emailAddresses', 'emailAddressesMultiple']],
                                'sql' => 'emailAddressesMultiple.lower = {value}',
                                'distinct' => true
                            ),
                            '<>' => array(
                                'leftJoins' => [['emailAddresses', 'emailAddressesMultiple']],
                                'sql' => 'emailAddressesMultiple.lower <> {value}',
                                'distinct' => true
                            ),
                            'IN' => array(
                                'leftJoins' => [['emailAddresses', 'emailAddressesMultiple']],
                                'sql' => 'emailAddressesMultiple.lower IN {value}',
                                'distinct' => true
                            ),
                            'NOT IN' => array(
                                'leftJoins' => [['emailAddresses', 'emailAddressesMultiple']],
                                'sql' => 'emailAddressesMultiple.lower NOT IN {value}',
                                'distinct' => true
                            ),
                            'IS NULL' => array(
                                'leftJoins' => [['emailAddresses', 'emailAddressesMultiple']],
                                'sql' => 'emailAddressesMultiple.lower IS NULL',
                                'distinct' => true
                            ),
                            'IS NOT NULL' => array(
                                'leftJoins' => [['emailAddresses', 'emailAddressesMultiple']],
                                'sql' => 'emailAddressesMultiple.lower IS NOT NULL',
                                'distinct' => true
                            )
                        ),
                        'orderBy' => 'emailAddresses.lower {direction}',
                    ),
                    $fieldName .'Data' => array(
                        'type' => 'text',
                        'notStorable' => true
                    ),
                    $fieldName .'IsOptedOut' => array(
                        'type' => 'bool',
                        'notStorable' => true,
                        'select' => 'emailAddresses.opt_out',
                        'where' => [
                            '= TRUE' => [
                                'sql' => 'emailAddresses.opt_out = true AND emailAddresses.opt_out IS NOT NULL'
                            ],
                            '= FALSE' => [
                                'sql' => 'emailAddresses.opt_out = false OR emailAddresses.opt_out IS NULL'
                            ]
                        ],
                        'orderBy' => 'emailAddresses.opt_out {direction}'
                    )
                ),
                'relations' => [
                    'emailAddresses' => [
                        'type' => 'manyMany',
                        'entity' => 'EmailAddress',
                        'relationName' => 'entityEmailAddress',
                        'midKeys' => [
                            'entityId',
                            'emailAddressId'
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
