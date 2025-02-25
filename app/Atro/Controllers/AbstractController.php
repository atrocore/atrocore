<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Controllers;

use Atro\Core\Container;

abstract class AbstractController
{
    protected ?string $name;

    private Container $container;

    private ?string $requestMethod;

    public static $defaultAction = 'index';

    public function __construct(Container $container, ?string $requestMethod = null, ?string $controllerName = null)
    {
        $this->container = $container;

        if (isset($requestMethod)) {
            $this->requestMethod = strtoupper($requestMethod);
        }

        if (empty($this->name)) {
            $name = $controllerName ?? get_class($this);
            if (preg_match('@\\\\([\w]+)$@', $name, $matches)) {
                $name = $matches[1];
            }
            $this->name = $name;
        }

        $this->checkControllerAccess();
    }

    protected function checkControllerAccess()
    {
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getUser(): \Espo\Entities\User
    {
        return $this->container->get('user');
    }

    protected function getAcl(): \Espo\Core\Acl
    {
        return $this->container->get('acl');
    }

    protected function getAclManager(): \Espo\Core\AclManager
    {
        return $this->container->get('aclManager');
    }

    protected function getConfig(): \Espo\Core\Utils\Config
    {
        return $this->container->get('config');
    }

    protected function getMetadata(): \Espo\Core\Utils\Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getServiceFactory(): \Espo\Core\ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }

    protected function getService(string $name)
    {
        return $this->getServiceFactory()->create($name);
    }

    protected function getEntityManager(): \Espo\ORM\EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    protected function getEventManager(): \Atro\Core\EventManager\Manager
    {
        return $this->getContainer()->get('eventManager');
    }
}
