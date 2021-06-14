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

namespace Espo\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Services\Base;
use Espo\Core\Utils\Util;

/**
 * Class Label
 */
class Label extends Base
{
    public function push(): bool
    {
        $data = [];
        $data['data'] = $this
            ->getEntityManager()
            ->nativeQuery("SELECT * FROM label WHERE is_customized=1 OR deleted=1")
            ->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($data['data'])) {
            throw new BadRequest($this->getInjection('language')->translate('nothingToPush', 'messages', 'Label'));
        }

        $data['appId'] = $this->getConfig()->get('appId');
        $data['siteUrl'] = $this->getConfig()->get('siteUrl');
        $data['smtpUsername'] = $this->getConfig()->get('smtpUsername');
        $data['emailFrom'] = $this->getConfig()->get('outboundEmailFromAddress');

        $ch = curl_init('https://pm.atrocore.com/api/v1/PushedTranslation');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    public function saveUnitsOfMeasure(string $language, array $labels): bool
    {
        $toRemove = [];
        $data = [];
        foreach ($labels as $k => $value) {
            $parts = explode('[.]', $k);
            if (!isset($parts[0]) || !isset($parts[1])) {
                throw new BadRequest("Wrong input data.");
            }

            $toRemove["Global.$parts[0]"] = true;
            $data["Global.$parts[0].$parts[1]"] = $value;
        }
        $toRemove = array_keys($toRemove);

        // delete old
        foreach ($toRemove as $item) {
            $preparedKeys = implode("','", array_keys($data));
            $this
                ->getEntityManager()
                ->nativeQuery("DELETE FROM label WHERE is_customized=1 AND name LIKE '$item%' AND module='custom' AND name NOT IN ('$preparedKeys')");
        }

        // update or create
        $language = Util::toCamelCase(strtolower($language));
        foreach ($data as $key => $value) {
            $entity = $this->getEntityManager()->getRepository('Label')->where(['name' => $key])->findOne();
            if (empty($entity)) {
                $entity = $this->getEntityManager()->getRepository('Label')->get();
                $entity->set('name', $key);
                $entity->set('module', 'custom');
            }

            if ($entity->get($language) === $value) {
                continue 1;
            }

            $entity->set('isCustomized', true);
            $entity->set($language, $value);

            $this->getEntityManager()->saveEntity($entity);
        }

        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
