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

use Espo\Core\Services\Base;
use Espo\Core\Utils\Metadata;

class GlobalSearch extends Base
{
    public const MAX = 20;

    public function find(string $query, int $offset, int $maxSize): array
    {
        $list = [];

        foreach ($this->getConfig()->get('globalSearchEntityList', []) as $entityType) {
            if (!$this->getInjection('acl')->checkScope($entityType, 'read')) {
                continue;
            }
            if (!$this->getMetadata()->get(['scopes', $entityType])) {
                continue;
            }

            $selectManager = $this->getInjection('selectManagerFactory')->create($entityType);
            $params = ['select' => ['id', 'name']];

            $selectManager->manageAccess($params);
            $selectManager->applyTextFilter($query, $params);

            $collection = $this->getEntityManager()->getRepository($entityType)->find($params);
            foreach ($collection as $entity) {
                if (count($list) >= self::MAX) {
                    break 2;
                }
                $list[] = array_merge($entity->toArray(), ['_scope' => $entity->getEntityType()]);
            }
        }

        return [
            'count' => count($list),
            'list'  => $list
        ];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('metadata');
        $this->addDependency('acl');
        $this->addDependency('selectManagerFactory');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }
}

