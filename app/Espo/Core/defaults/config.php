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
    'isInstalled'                           => false,
    'passwordSalt'                          => 'some-salt',
    'database'                              => [
        'driver'   => 'pdo_mysql',
        'host'     => 'localhost',
        'port'     => '',
        'charset'  => 'utf8mb4',
        'dbname'   => '',
        'user'     => '',
        'password' => ''
    ],
    'queueManagerWorkersCount'              => 4,
    'currencyRates'                         => [],
    'outboundEmailFromAddress'              => '',
    'smtpServer'                            => '',
    'smtpAuth'                              => true,
    'smtpSecurity'                          => '',
    'smtpUsername'                          => '',
    'smtpPassword'                          => '',
    'logger'                                => [
        'path'          => 'data/logs/atro.log',
        'level'         => 'WARNING', /** DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY */
        'rotation'      => true,
        'maxFileNumber' => 30,
    ],
    'adminNotifications'                    => true,
    'adminNotificationsNewVersion'          => true,
    'adminNotificationsCronIsNotConfigured' => true,
    'adminNotificationsNewExtensionVersion' => true,
    'assignmentNotifications'               => true,
    'assignmentEmailNotifications'          => false,
    "portalStreamEmailNotifications"        => true,
    'disabledCountQueryEntityList'          => [],
    'followCreatedEntities'                 => false,
    'b2cMode'                               => false,
    'restrictedMode'                        => false,
    'authTokenLifetime'                     => 0,
    'authTokenMaxIdleTime'                  => 120,
    'userNameRegularExpression'             => '[^a-z0-9\-@_\.\s]',
    'addressFormat'                         => 1,
    'displayListViewRecordCount'            => true,
    'calendarEntityList'                    => ['Meeting', 'Call', 'Task'],
    'activitiesEntityList'                  => ['Meeting', 'Call'],
    'historyEntityList'                     => ['Meeting', 'Call', 'Email'],
    'cleanupJobPeriod'                      => '1 month',
    'cleanupActionHistoryPeriod'            => '15 days',
    'cleanupAuthTokenPeriod'                => '1 month',
    'currencyFormat'                        => 2,
    'currencyDecimalPlaces'                 => 2,
    'aclStrictMode'                         => false,
    'inlineAttachmentUploadMaxSize'         => 20,
    'textFilterUseContainsForVarchar'       => false,
    'tabColorsDisabled'                     => false,
    'emailKeepParentTeamsEntityList'        => ['Case'],
    'noteDeleteThresholdPeriod'             => '1 month',
    'noteEditThresholdPeriod'               => '7 days',
    'recordsPerPage'                        => 50,
    'recordsPerPageSmall'                   => 20,
    'lastViewedCount'                       => 20,
    'useCache'                              => true,
    'applicationName'                       => 'AtroPIM',
    'outboundEmailFromName'                 => 'AtroPIM',
    'attachmentDuplicates'                  => 'notAllowByContentAndName',
    'defaultFileStorage'                    => 'UploadDir',
    'filesPath'                             => 'upload/files/',
    'thumbnailsPath'                        => 'upload/thumbnails/',
    'chunkFileSize'                         => 2,
    'fileUploadStreamCount'                 => 3,
    'isUploadPrivate'                       => true,
    'smtpPort'                              => 25,
    'languageList'                          => [
        'en_US',
        'de_DE',
        'uk_UA'
    ],
    'currencyList'                          => [
        0 => 'EUR'
    ],
    'defaultCurrency'                       => 'EUR',
    'baseCurrency'                          => 'EUR',
    'globalSearchEntityList'                => [],
    'tabList'                               => [],
    'quickCreateList'                       => [],
    'theme'                                 => 'TreoDarkTheme',
    'dashboardLayout'                       => [
        (object)[
            'name'   => 'My AtroPIM',
            'layout' => []
        ]
    ],
    'cronMassUpdateMax'                     => 3000, // count of max massUpdate items for CRON
    'checkForConflicts'                     => true,
    'localeId'                              => '1',
    'mainLanguage'                          => 'en_US',
    'whitelistedExtensions' => [
        'aif',
        'cda',
        'mid',
        'midi',
        'mp3',
        'mpa',
        'ogg',
        'wav',
        'wma',
        'wpl',
        '7z',
        'arj',
        'deb',
        'pkg',
        'rar',
        'rpm',
        'tar.gz',
        'z',
        'zip',
        'bin',
        'dmg',
        'iso',
        'toast',
        'vcd',
        'csv',
        'dat',
        'db',
        'dbf',
        'log',
        'mdb',
        'sav',
        'tar',
        'xml',
        'email',
        'eml',
        'emlx',
        'msg',
        'oft',
        'ost',
        'pst',
        'vcf',
        'fnt',
        'fon',
        'otf',
        'ttf',
        'ai',
        'bmp',
        'gif',
        'ico',
        'jpeg',
        'jpg',
        'png',
        'ps',
        'psd',
        'svg',
        'tif',
        'tiff',
        'webp',
        'key',
        'odp',
        'pps',
        'ppt',
        'pptx',
        'ods',
        'xls',
        'xlsm',
        'xlsx',
        '3g2',
        '3gp',
        'avi',
        'flv',
        'h264',
        'm4v',
        'mkv',
        'mov',
        'mp4',
        'mpg',
        'mpeg',
        'rm',
        'swf',
        'vob',
        'webm',
        'wmv',
        'doc',
        'docx',
        'odt',
        'pdf',
        'rtf',
        'tex',
        'txt',
        'wpd',
        'json'
    ],
];

