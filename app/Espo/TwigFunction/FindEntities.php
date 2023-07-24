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

namespace Espo\TwigFunction;

use Espo\Core\Twig\AbstractTwigFunction;
use Espo\ORM\EntityCollection;

class FindEntities extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('entityManager');
    }

    /**
     * @param string $entityName
     * @param array  $where
     * @param string $orderField
     * @param string $orderDirection
     * @param int    $offset
     * @param int    $limit
     *
     * @return EntityCollection
     */
    public function run(...$input)
    {
        if (empty($input[0]) || empty($input[1])) {
            return null;
        }

        $entityName = $input[0];
        $where = $input[1];
        $orderField = $input[2] ?? 'id';
        $orderDirection = $input[3] ?? 'ASC';
        $offset = $input[4] ?? 0;
        $limit = $input[5] ?? \PHP_INT_MAX;

        return $this->getInjection('entityManager')->getRepository($entityName)
            ->where((array)$where)
            ->order((string)$orderField, (string)$orderDirection)
            ->limit((int)$offset, (int)$limit)
            ->find();
    }
}
