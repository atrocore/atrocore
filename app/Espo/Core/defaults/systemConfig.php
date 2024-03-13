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

return array (    'defaultPermissions' =>
    array (
        'dir' => '0775',
        'file' => '0664',
        'user' => '',
        'group' => '',
    ),

    'permissionMap' => array(

        /** array('0664', '0775') */
        'writable' => array(
            'data',
            'custom',
        ),

        /** array('0644', '0755') */
        'readable' => array(
            'api',
            'application',
            'client',
            'vendor',
            'index.php',
            'cron.php',
            'rebuild.php',
            'main.html',
            'reset.html',
        ),
    ),
    'jobMaxPortion' => 15, /** Max number of jobs per one execution. */
    'jobPeriod' => 7800, /** Max execution time (in seconds) allocated for a sinle job. If exceeded then set to Failed.*/
    'jobPeriodForActiveProcess' => 36000, /** Max execution time (in seconds) allocated for a sinle job with active process. If exceeded then set to Failed.*/
    'jobRerunAttemptNumber' => 1, /** Number of attempts to re-run failed jobs. */
    'cronMinInterval' => 4, /** Min interval (in seconds) between two cron runs. */
    'crud' => array(
        'get' => 'read',
        'post' => 'create',
        'put' => 'update',
        'patch' => 'patch',
        'delete' => 'delete',
    ),
    'systemUser' => array(
        'id' => 'system',
        'userName' => 'system',
        'firstName' => '',
        'lastName' => 'System',
    ),
    'systemItems' =>
    array (
        'systemItems',
        'adminItems',
        'configPath',
        'cachePath',
        'database',
        'crud',
        'logger',
        'isInstalled',
        'defaultPermissions',
        'systemUser',
        'permissionMap',
        'permissionRules',
        'passwordSalt',
        'cryptKey',
        'restrictedMode',
        'userLimit',
        'stylesheet',
        'userItems'
    ),
    'adminItems' =>
    array (
        'devMode',
        'smtpServer',
        'smtpPort',
        'smtpAuth',
        'smtpSecurity',
        'smtpUsername',
        'smtpPassword',
        'jobMaxPortion',
        'jobPeriod',
        'jobRerunAttemptNumber',
        'cronMinInterval',
        'adminPanelIframeUrl',
        'authTokenLifetime',
        'authTokenMaxIdleTime',
        'cleanupJobPeriod',
        'cleanupActionHistoryPeriod',
        'adminNotifications',
        'adminNotificationsNewVersion',
        'adminNotificationsCronIsNotConfigured',
        'adminNotificationsNewExtensionVersion',
        'leadCaptureAllowOrigin'
    ),
    'userItems' =>
    array (
        'outboundEmailFromAddress',
        'outboundEmailFromName',
        'integrations'
    ),
    'isInstalled' => false,
);
