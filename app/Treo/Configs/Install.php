<?php

declare(strict_types=1);

namespace Treo\Configs;

return [
    'requirements' => [
        'phpVersion'   => '7.1',
        'phpRequires'  => [
            'json',
            'openssl',
            'pdo_mysql',
            'mbstring',
            'zip',
            'gd',
            'curl',
            'xml',
            'exif'
        ],
        'phpSettings'  => [
            'max_execution_time'  => 180,
            'max_input_time'      => 180,
            'memory_limit'        => '256M',
            'post_max_size'       => '20M',
            'upload_max_filesize' => '20M'
        ],
        'mysqlVersion' => '5.5.3'
    ]
];
