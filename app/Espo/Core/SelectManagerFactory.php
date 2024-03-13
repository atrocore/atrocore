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

declare(strict_types=1);

namespace Espo\Core;

use Espo\Core\Utils\Util;

class SelectManagerFactory
{
    /**
     * @var Container
     */
    private $container;

    /**
     * SelectManagerFactory constructor
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Create
     *
     * @param string $entityType
     * @param null   $user
     *
     * @return mixed
     */
    public function create($entityType, $user = null)
    {
        $normalizedName = Util::normilizeClassName($entityType);

        $className = '\\Espo\\Custom\\SelectManagers\\' . $normalizedName;
        if (!class_exists($className)) {
            $moduleName = $this->container->get('metadata')->getScopeModuleName($entityType);
            if ($moduleName) {
                $className = '\\' . $moduleName . '\\SelectManagers\\' . $normalizedName;
            }
            if (!class_exists($className)) {
                $className = '\\Atro\\SelectManagers\\' . $normalizedName;
            }
            if (!class_exists($className)) {
                $className = '\\Espo\\SelectManagers\\' . $normalizedName;
            }
            if (!class_exists($className)) {
                $className = '\\Espo\\Core\\SelectManagers\\Base';
            }
        }

        if ($user) {
            $acl = $this->container->get('aclManager')->createUserAcl($user);
        } else {
            $acl = $this->container->get('acl');
            $user = $this->container->get('user');
        }

        $selectManager = new $className(
            $this->container->get('entityManager'),
            $user,
            $acl,
            $this->container->get('aclManager'),
            $this->container->get('metadata'),
            $this->container->get('config'),
            $this->container->get('injectableFactory')
        );
        $selectManager->setEntityType($entityType);
        $selectManager->setSelectManagerFactory($this);

        return $selectManager;
    }
}
