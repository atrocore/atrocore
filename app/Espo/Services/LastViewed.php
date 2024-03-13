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

namespace Espo\Services;

use \Espo\ORM\Entity;

class LastViewed extends \Espo\Core\Services\Base
{
    protected function init()
    {
        parent::init();
        $this->addDependency('serviceFactory');
        $this->addDependency('metadata');
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function get(array $params = [])
    {
        $entityManager = $this->getInjection('entityManager');

        $maxSize = $params['maxSize'] ?? $this->getConfig()->get('lastViewedCount', 20);

        $offset = $params['offset'] ?? 0;

        $actionHistoryRecordService = $this->getInjection('serviceFactory')->create('ActionHistoryRecord');

        $scopes = $this->getInjection('metadata')->get('scopes');

        $targetTypeList = array_filter(array_keys($scopes), function ($item) use ($scopes) {
            return !empty($scopes[$item]['object']) && empty($scopes[$item]['hideLastViewed']);
        });

        $collection = $this->getEntityManager()->getRepository('ActionHistoryRecord')->where(array(
            'userId' => $this->getUser()->id,
            'action' => 'read',
            'targetType' => $targetTypeList
        ))->order(3, true)->limit($offset, $maxSize)->select([
            'targetId', 'targetType', 'MAX:number'
        ])->groupBy([
            'targetId', 'targetType'
        ])->find();

        $count = $this->getEntityManager()->getRepository('ActionHistoryRecord')->where(array(
            'userId' => $this->getUser()->id,
            'action' => 'read',
            'targetType' => $targetTypeList
        ))->select([
            'targetId', 'targetType'
        ])->groupBy([
            'targetId', 'targetType'
        ])->find()->count();

        foreach ($collection as $i => $entity) {
            $actionHistoryRecordService->loadParentNameFields($entity);
            $entity->id = $offset + $i;
        }

        return array(
            'total' => $count,
            'collection' => $collection
        );
    }
}

