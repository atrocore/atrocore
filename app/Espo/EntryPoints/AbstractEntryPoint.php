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

namespace Espo\EntryPoints;

use Espo\Core\Acl;
use Espo\Core\Utils\ClientManager;
use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\NumberUtil;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Atro\Core\Container;
use Espo\ORM\EntityManager;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\File\Manager;
use Espo\Core\Utils\Metadata;

/**
 * Class AbstractEntryPoint
 */
abstract class AbstractEntryPoint
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var bool
     */
    public static $authRequired = true;

    /**
     * @var bool
     */
    public static $notStrictAuth = false;

    /**
     * AbstractEntryPoint constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @return User
     */
    protected function getUser(): User
    {
        return $this->getContainer()->get('user');
    }

    /**
     * @return Acl
     */
    protected function getAcl(): Acl
    {
        return $this->getContainer()->get('acl');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    /**
     * @return ServiceFactory
     */
    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getContainer()->get('serviceFactory');
    }

    /**
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    /**
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    /**
     * @return DateTime
     */
    protected function getDateTime(): DateTime
    {
        return $this->getContainer()->get('dateTime');
    }

    /**
     * @return NumberUtil
     */
    protected function getNumber(): NumberUtil
    {
        return $this->getContainer()->get('number');
    }

    /**
     * @return Manager
     */
    protected function getFileManager(): Manager
    {
        return $this->getContainer()->get('fileManager');
    }

    /**
     * @return Language
     */
    protected function getLanguage(): Language
    {
        return $this->getContainer()->get('language');
    }

    /**
     * @return ClientManager
     */
    protected function getClientManager(): ClientManager
    {
        return $this->getContainer()->get('clientManager');
    }
}
