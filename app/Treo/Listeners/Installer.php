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

declare(strict_types=1);

namespace Treo\Listeners;

use Treo\Core\EventManager\Event;

/**
 * Installer listener
 */
class Installer extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterInstallSystem(Event $event)
    {
        // generate Treo ID
        $this->generateTreoId();

        // create files in data dir
        $this->createDataFiles();

        // create scheduled jobs
        $this->createScheduledJobs();

        /**
         * Run after install script if it needs
         */
        $file = 'data/after_install_script.php';
        if (file_exists($file)) {
            include_once $file;
            unlink($file);
        }
    }

    /**
     * Generate Treo ID
     */
    protected function generateTreoId(): void
    {
        // generate id
        $treoId = \Treo\Services\Installer::generateTreoId();

        // set to config
        $this->getConfig()->set('treoId', $treoId);
        $this->getConfig()->save();

        // set treo ID to packagist repository
        $composeData = json_decode(file_get_contents('composer.json'), true);
        $composeData['repositories'][0]['url'] = str_replace('common', $treoId, $composeData['repositories'][0]['url']);
        file_put_contents('composer.json', json_encode($composeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Create needed files in data directory
     */
    protected function createDataFiles(): void
    {
        file_put_contents('data/notReadCount.json', '{}');
        file_put_contents('data/popupNotifications.json', '{}');
    }

    /**
     * Create scheduled jobs
     */
    protected function createScheduledJobs(): void
    {
        $this
            ->getEntityManager()
            ->nativeQuery(
                "INSERT INTO scheduled_job (id, name, job, status, scheduling) VALUES ('ComposerAutoUpdate', 'Auto-updating of modules', 'ComposerAutoUpdate', 'Active', '0 0 * * SUN')"
            );
        $this
            ->getEntityManager()
            ->nativeQuery(
                "INSERT INTO scheduled_job (id, name, job, status, scheduling) VALUES ('TreoCleanup','Unused data cleanup. Deleting old data and unused db tables, db columns, etc.','TreoCleanup','Active','0 0 1 * *')"
            );

        $this
            ->getEntityManager()
            ->nativeQuery(
                "INSERT INTO scheduled_job (id, name, job, status, scheduling) VALUES ('RestApiDocs','Generate REST API docs','RestApiDocs','Active','0 */2 * * *')"
            );
    }
}
