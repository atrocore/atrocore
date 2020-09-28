<?php

return array(

    'Autofollow' => array(
        'fields' => array(
            'id' => array(
                'type' => 'id',
                'dbType' => 'int',
                'len' => '11',
                'autoincrement' => true,
                'unique' => true,
            ),
            'entityType' => array(
                'type' => 'varchar',
                'len' => '100',
                'index' => 'entityType',
            ),
            'userId' => array(
                'type' => 'varchar',
                'len' => '24',
                'index' => true,
            )
        )
    )

);

