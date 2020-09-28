<?php

return array(

    'unset' => array(
        '__APPEND__',
        'Preferences'
    ),
    'unsetIgnore' => [
        '__APPEND__',
        ['Preferences', 'fields', 'id'],
        ['Preferences', 'fields', 'data']
    ],
    'Preferences' => array(
        'fields' => array(
            'id' => array(
                'dbType' => 'varchar',
                'len' => 24,
                'type' => 'id'
            ),
            'data' => array(
                'type' => 'text'
            )
        )
    )

);

