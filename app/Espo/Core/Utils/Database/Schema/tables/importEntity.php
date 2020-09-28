<?php

return array(

    'ImportEntity' => array(
        'fields' => array(
            'id' => array(
                'type' => 'id',
                'dbType' => 'int',
                'len' => '11',
                'autoincrement' => true,
                'unique' => true
            ),
            'entityId' => array(
                'type' => 'varchar',
                'len' => '24',
                'index' => 'entity'
            ),
            'entityType' => array(
                'type' => 'varchar',
                'len' => '100',
                'index' => 'entity'
            ),
            'importId' => array(
                'type' => 'varchar',
                'len' => '24',
                'index' => true
            ),
            'isImported' => array(
                'type' => 'bool'
            ),
            'isUpdated' => array(
                'type' => 'bool'
            ),
            'isDuplicate' => array(
                'type' => 'bool'
            ),
        ),
        "indexes" => array(
            "entityImport" => array(
                "columns" => ["importId", "entityType"]
            )
        )
    ),

);

