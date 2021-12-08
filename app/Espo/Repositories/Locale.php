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

namespace Espo\Repositories;

use Espo\Core\DataManager;
use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class Locale extends Base
{
    public const CACHE_FILE = 'data/cache/locales.json';

    public function getCachedLocales(): array
    {
        if (file_exists(self::CACHE_FILE)) {
            return Json::decode(file_get_contents(self::CACHE_FILE), true);
        }

        $result = [];
        foreach ($this->find() as $locale) {
            $result[$locale->get('id')] = $locale->toArray();
        }

        file_put_contents(self::CACHE_FILE, Json::encode($result));

        return $result;
    }

    public function refreshCache(): void
    {
        if (!file_exists(self::CACHE_FILE)) {
            return;
        }

        unlink(self::CACHE_FILE);

        $this->getConfig()->set('cacheTimestamp', time());
        $this->getConfig()->save();
        DataManager::pushPublicData('dataTimestamp', time());
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->refreshCache();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->refreshCache();
    }
}
