<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

return [
    'isInstalled'                            => false,
    'passwordSalt'                           => 'some-salt',
    'database'                               => [
        'driver'   => 'pdo_mysql',
        'host'     => 'localhost',
        'port'     => '',
        'charset'  => 'utf8mb4',
        'dbname'   => '',
        'user'     => '',
        'password' => ''
    ],
    'dateFormat'                             => 'MM/DD/YYYY',
    'timeFormat'                             => 'hh:mm a',
    'weekStart'                              => 0,
    'currencyRates'                          => [],
    'outboundEmailIsShared'                  => true,
    'outboundEmailFromAddress'               => '',
    'smtpServer'                             => '',
    'smtpAuth'                               => true,
    'smtpSecurity'                           => '',
    'smtpUsername'                           => '',
    'smtpPassword'                           => '',
    'logger'                                 => [
        'path'          => 'data/logs/espo.log',
        'level'         => 'WARNING', /** DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY */
        'rotation'      => true,
        'maxFileNumber' => 30,
    ],
    'exportDisabled'                         => false,
    'adminNotifications'                     => true,
    'adminNotificationsNewVersion'           => true,
    'adminNotificationsCronIsNotConfigured'  => true,
    'adminNotificationsNewExtensionVersion'  => true,
    'assignmentEmailNotifications'           => false,
    'assignmentEmailNotificationsEntityList' => ['Lead', 'Opportunity', 'Task', 'Case'],
    'assignmentNotificationsEntityList'      => ['Meeting', 'Call', 'Task', 'Email'],
    "portalStreamEmailNotifications"         => true,
    'streamEmailNotificationsEntityList'     => ['Case'],
    'streamEmailNotificationsTypeList'       => ['Post', 'Status', 'EmailReceived'],
    'emailMessageMaxSize'                    => 10,
    'notificationsCheckInterval'             => 10,
    'disabledCountQueryEntityList'           => ['Email'],
    'maxEmailAccountCount'                   => 2,
    'followCreatedEntities'                  => false,
    'b2cMode'                                => false,
    'restrictedMode'                         => false,
    'massEmailMaxPerHourCount'               => 100,
    'personalEmailMaxPortionSize'            => 10,
    'inboundEmailMaxPortionSize'             => 20,
    'authTokenLifetime'                      => 0,
    'authTokenMaxIdleTime'                   => 120,
    'userNameRegularExpression'              => '[^a-z0-9\-@_\.\s]',
    'addressFormat'                          => 1,
    'displayListViewRecordCount'             => true,
    'calendarEntityList'                     => ['Meeting', 'Call', 'Task'],
    'activitiesEntityList'                   => ['Meeting', 'Call'],
    'historyEntityList'                      => ['Meeting', 'Call', 'Email'],
    'cleanupJobPeriod'                       => '1 month',
    'cleanupActionHistoryPeriod'             => '15 days',
    'cleanupAuthTokenPeriod'                 => '1 month',
    'currencyFormat'                         => 2,
    'currencyDecimalPlaces'                  => 2,
    'aclStrictMode'                          => false,
    'aclAllowDeleteCreated'                  => false,
    'inlineAttachmentUploadMaxSize'          => 20,
    'textFilterUseContainsForVarchar'        => false,
    'tabColorsDisabled'                      => false,
    'massPrintPdfMaxCount'                   => 50,
    'emailKeepParentTeamsEntityList'         => ['Case'],
    'recordListMaxSizeLimit'                 => 200,
    'noteDeleteThresholdPeriod'              => '1 month',
    'noteEditThresholdPeriod'                => '7 days',
    'recordsPerPage'                         => 50,
    'recordsPerPageSmall'                    => 20,
    'lastViewedCount'                        => 20,
    'decimalMark'                            => '.',
    'thousandSeparator'                      => ',',
    'useCache'                               => true,
    'applicationName'                        => 'AtroCORE',
    'outboundEmailFromName'                  => 'AtroCORE',
    'defaultFileStorage'                     => 'UploadDir',
    'filesPath'                              => 'upload/files/',
    'thumbnailsPath'                         => 'upload/thumbnails/',
    'chunkFileSize'                          => 2,
    'fileUploadStreamCount'                  => 3,
    'isUploadPrivate'                        => true,
    'smtpPort'                               => 25,
    'languageList'                           => [
        'en_US',
        'de_DE',
        'ru_RU'
    ],
    'language'                               => 'en_US',
    'currencyList'                           => [
        0 => 'EUR'
    ],
    'defaultCurrency'                        => 'EUR',
    'baseCurrency'                           => 'EUR',
    'authenticationMethod'                   => 'Espo',
    'globalSearchEntityList'                 => [],
    'tabList'                                => [],
    'quickCreateList'                        => [],
    'theme'                                  => 'TreoDarkTheme',
    'dashboardLayout'                        => [
        (object)[
            'name'   => 'My AtroCORE',
            'layout' => []
        ]
    ],
    'webMassUpdateMax'                       => 200, // count of max massUpdate items for WEB
    'cronMassUpdateMax'                      => 3000, // count of max massUpdate items for CRON
    'developMode'                            => false,
    'exportDelimiter'                        => ';',
    'timeZone'                               => 'UTC',
    'unitsOfMeasure'                         => (object)[
        'Length'                    => (object)[
            'unitList'  => [
                0 => 'mm',
                1 => 'cm',
                2 => 'dm',
                3 => 'm',
                4 => 'km'
            ],
            'baseUnit'  => 'mm',
            'unitRates' => (object)[
                'cm' => 1,
                'dm' => 1,
                'm'  => 1,
                'km' => 1
            ]
        ],
        'Mass'                      => (object)[
            'unitList'  => [
                0 => 'mg',
                1 => 'g',
                2 => 'kg'
            ],
            'baseUnit'  => 'mg',
            'unitRates' => (object)[
                'g'  => 1,
                'kg' => 1
            ]
        ],
        'Time'                      => (object)[
            'unitList'  => [
                0 => 's',
                1 => 'm',
                2 => 'h'
            ],
            'baseUnit'  => 's',
            'unitRates' => (object)[
                'm' => 1,
                'h' => 1
            ]
        ],
        'Electric Current'          => (object)[
            'unitList'  => [
                0 => 'A'
            ],
            'baseUnit'  => 'A',
            'unitRates' => (object)[

            ]
        ],
        'Thermodynamic Temperature' => (object)[
            'unitList'  => [
                0 => 'K',
                1 => 'C'
            ],
            'baseUnit'  => 'K',
            'unitRates' => (object)[
                'C' => 1
            ]
        ],
        'Amount Of Substance'       => (object)[
            'unitList'  => [
                0 => 'mol'
            ],
            'baseUnit'  => 'mol',
            'unitRates' => (object)[

            ]
        ],
        'Luminous Intensity'        => (object)[
            'unitList'  => [
                0 => 'cd'
            ],
            'baseUnit'  => 'cd',
            'unitRates' => (object)[

            ]
        ]
    ]
];

