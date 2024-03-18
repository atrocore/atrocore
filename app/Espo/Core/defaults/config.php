<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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
    'amountOfDbDumps'                       => 14,
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
    'notificationsMaxDays'                  => 21,
    'queueItemsMaxDays'                     => 21,
    'jobsMaxDays'                           => 21,
    'scheduledJobLogsMaxDays'               => 21,
    'authLogsMaxDays'                       => 21,
    'actionHistoryMaxDays'                  => 21,
    'deletedAttachmentsMaxDays'             => 14,
    'deletedItemsMaxDays'                   => 14,
    'cleanDbSchema'                         => true,
    'cleanEntityTeam'                       => true,
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
    'globalSearchEntityList'                => ['Asset', 'AssetCategory', 'Library'],
    'tabList'                               => ['Asset', 'AssetCategory', 'Library'],
    'twoLevelTabList'                       => ['Asset', 'AssetCategory', 'Library'],
    'quickCreateList'                       => ['Asset', 'AssetCategory', 'Library'],
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
    'mainLanguage'                          => 'en_US'
];

