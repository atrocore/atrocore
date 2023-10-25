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

namespace Espo\Services;

use Espo\Core\Application;
use Espo\Core\Services\Base;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Util;

class App extends Base
{
    public static function createRebuildJob(\Doctrine\DBAL\Connection $connection): void
    {
        $connection->createQueryBuilder()
            ->insert($connection->quoteIdentifier('job'))
            ->setValue('id', ':id')
            ->setValue('execute_time', ':executeTime')
            ->setValue('created_at', ':executeTime')
            ->setValue('method_name', ':methodName')
            ->setValue('service_name', ':serviceName')
            ->setParameters([
                'id'          => Util::generateId(),
                'executeTime' => (new \DateTime())->modify('+1 minutes')->format('Y-m-d H:i:s'),
                'methodName'  => 'rebuild',
                'serviceName' => 'App'
            ])
            ->executeQuery();
    }

    public function rebuild($data = null, $targetId = null, $targetType = null): void
    {
        if (Application::isSystemUpdating()) {
            self::createRebuildJob($this->getInjection('connection'));
        } else {
            $this->getInjection('dataManager')->rebuild();
        }
    }

    public function getUserData(): array
    {
        $preferencesData = $this->getInjection('preferences')->getValueMap();
        unset($preferencesData->smtpPassword);

        $user = $this->getUser();
        if (!$user->has('teamsIds')) {
            $user->loadLinkMultipleField('teams');
        }

        $userData = $user->getValueMap();

        $settings = (object)[];
        foreach ($this->getConfig()->get('userItems') as $item) {
            $settings->$item = $this->getConfig()->get($item);
        }

        if ($this->getUser()->isAdmin()) {
            foreach ($this->getConfig()->get('adminItems') as $item) {
                if ($this->getConfig()->has($item)) {
                    $settings->$item = $this->getConfig()->get($item);
                }
            }
        }

        $settingsFieldDefs = $this->getInjection('metadata')->get('entityDefs.Settings.fields', []);
        foreach ($settingsFieldDefs as $field => $d) {
            if ($d['type'] === 'password') {
                unset($settings->$field);
            }
        }

        unset($userData->authTokenId);
        unset($userData->password);

        return [
            'user'        => $userData,
            'acl'         => $this->getInjection('acl')->getMap(),
            'preferences' => $preferencesData,
            'token'       => $this->getUser()->get('token'),
            'settings'    => $settings,
            'language'    => Language::detectLanguage($this->getConfig(), $this->getInjection('preferences')),
            'appParams'   => [
                'maxUploadSize' => $this->getMaxUploadSize() / 1024.0 / 1024.0
            ]
        ];
    }

    public function sendTestEmail(array $data): bool
    {
        $this
            ->getInjection('mailSender')
            ->send(
                [
                    'subject' => 'Test Email',
                    'body'    => 'Test Email',
                    'isHtml'  => false,
                    'to'      => $data['emailAddress']
                ]
            );

        return true;
    }

    protected function getMaxUploadSize(): int
    {
        $maxSize = 0;

        $postMaxSize = $this->convertPHPSizeToBytes(ini_get('post_max_size'));
        if ($postMaxSize > 0) {
            $maxSize = $postMaxSize;
        }
        $attachmentUploadMaxSize = $this->getConfig()->get('attachmentUploadMaxSize');
        if ($attachmentUploadMaxSize && (!$maxSize || $attachmentUploadMaxSize < $maxSize)) {
            $maxSize = $attachmentUploadMaxSize;
        }

        return $maxSize;
    }

    /**
     * @param mixed $size
     *
     * @return int
     */
    protected function convertPHPSizeToBytes($size): int
    {
        if (is_numeric($size)) {
            return $size;
        }

        $suffix = substr($size, -1);
        $value = substr($size, 0, -1);
        switch (strtoupper($suffix)) {
            case 'P':
                $value *= 1024;
            case 'T':
                $value *= 1024;
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
                break;
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('pdo');
        $this->addDependency('connection');
        $this->addDependency('preferences');
        $this->addDependency('acl');
        $this->addDependency('metadata');
        $this->addDependency('mailSender');
        $this->addDependency('dataManager');
    }
}
