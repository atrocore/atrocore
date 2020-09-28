<?php

declare(strict_types=1);

namespace Treo\Configs;

return [
    'passwordSalt'           => 'some-salt',
    'database'               => [
        'driver'   => 'pdo_mysql',
        'host'     => 'localhost',
        'port'     => '',
        'charset'  => 'utf8mb4',
        'dbname'   => '',
        'user'     => '',
        'password' => ''
    ],
    'recordsPerPage'         => 50,
    'recordsPerPageSmall'    => 20,
    'lastViewedCount'        => 20,
    'decimalMark'            => '.',
    'thousandSeparator'      => ',',
    'useCache'               => true,
    'applicationName'        => 'TreoCore',
    'outboundEmailFromName'  => 'TreoCore',
    'smtpPort'               => 25,
    'languageList'           => [
        'en_US',
        'de_DE'
    ],
    'language'               => 'en_US',
    'currencyList'           => [
        0 => 'EUR'
    ],
    'defaultCurrency'        => 'EUR',
    'baseCurrency'           => 'EUR',
    'authenticationMethod'   => 'Espo',
    'globalSearchEntityList' => [],
    'tabList'                => [],
    'quickCreateList'        => [],
    'theme'                  => 'TreoDarkTheme',
    'dashboardLayout'        => [
        (object)[
            'name'   => 'My TreoCore',
            'layout' => []
        ]
    ],
    'webMassUpdateMax'       => 200, // count of max massUpdate items for WEB
    'cronMassUpdateMax'      => 3000, // count of max massUpdate items for CRON
    'developMode'            => false,
    'exportDelimiter'        => ';',
    'timeZone'               => 'UTC',
    'unitsOfMeasure' => (object) [
        'Length' => (object) [
            'unitList' => [
                0 => 'mm',
                1 => 'cm',
                2 => 'dm',
                3 => 'm',
                4 => 'km'
            ],
            'baseUnit' => 'mm',
            'unitRates' => (object) [
                'cm' => 1,
                'dm' => 1,
                'm' => 1,
                'km' => 1
            ]
        ],
        'Mass' => (object) [
            'unitList' => [
                0 => 'mg',
                1 => 'g',
                2 => 'kg'
            ],
            'baseUnit' => 'mg',
            'unitRates' => (object) [
                'g' => 1,
                'kg' => 1
            ]
        ],
        'Time' => (object) [
            'unitList' => [
                0 => 's',
                1 => 'm',
                2 => 'h'
            ],
            'baseUnit' => 's',
            'unitRates' => (object) [
                'm' => 1,
                'h' => 1
            ]
        ],
        'Electric Current' => (object) [
            'unitList' => [
                0 => 'A'
            ],
            'baseUnit' => 'A',
            'unitRates' => (object) [

            ]
        ],
        'Thermodynamic Temperature' => (object) [
            'unitList' => [
                0 => 'K',
                1 => 'C'
            ],
            'baseUnit' => 'K',
            'unitRates' => (object) [
                'C' => 1
            ]
        ],
        'Amount Of Substance' => (object) [
            'unitList' => [
                0 => 'mol'
            ],
            'baseUnit' => 'mol',
            'unitRates' => (object) [

            ]
        ],
        'Luminous Intensity' => (object) [
            'unitList' => [
                0 => 'cd'
            ],
            'baseUnit' => 'cd',
            'unitRates' => (object) [

            ]
        ]
    ]
];
