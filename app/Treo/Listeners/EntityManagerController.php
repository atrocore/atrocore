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
 * Website: https://treolabs.com
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
 * Class EntityManagerController
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class EntityManagerController extends AbstractListener
{
    /**
     * @var array
     */
    protected $scopesConfig = null;

    /**
     * @param Event $event
     */
    public function afterActionCreateEntity(Event $event)
    {
        // update scopes
        $this->updateScope(get_object_vars($event->getArgument('data')));

        if ($event->getArgument('result')) {
            $this->getContainer()->get('dataManager')->rebuild();
        }
    }

    /**
     * @param Event $event
     */
    public function afterActionUpdateEntity(Event $event)
    {
        $this->afterActionCreateEntity($event);
    }

    /**
     * Set data to scope
     *
     * @param array $data
     */
    protected function updateScope(array $data): void
    {
        // prepare name
        $name = trim(ucfirst($data['name']));

        $this
            ->getContainer()
            ->get('metadata')
            ->set('scopes', $name, $this->getPreparedScopesData($data));

        // save
        $this->getContainer()->get('metadata')->save();
    }

    /**
     * Get prepared scopes data
     *
     * @param array $data
     *
     * @return array
     */
    protected function getPreparedScopesData(array $data): array
    {
        // prepare result
        $scopeData = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $this->getScopesConfig()['edited'])) {
                $scopeData[$key] = $value;
            }
        }

        return $scopeData;
    }

    /**
     * Get scopes config
     *
     * @return array
     */
    protected function getScopesConfig(): array
    {
        if (is_null($this->scopesConfig)) {
            // prepare result
            $this->scopesConfig = include CORE_PATH . '/Treo/Configs/Scopes.php';
        }

        return $this->scopesConfig;
    }
}
