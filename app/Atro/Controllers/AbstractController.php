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
use Atro\Core\Utils\Config;

abstract class AbstractController
{
    protected ?string $name;

    private Container $container;

    private ?string $requestMethod;

    protected string $defaultRecordServiceName = 'Record';

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

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getMetadata(): \Atro\Core\Utils\Metadata
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

    protected function prepareWhereQuery($where)
    {
        if (is_string($where)) {
            $where = json_decode(str_replace(['"{', '}"', '\"', '\n', '\t'], ['{', '}', '"', '', ''], $where), true);
        }

        return $where;
    }

    protected function fetchListParamsFromRequest(&$params, $request, $data)
    {
        if ($request->get('primaryFilter')) {
            $params['primaryFilter'] = $request->get('primaryFilter');
        }
        if ($request->get('boolFilterList')) {
            $params['boolFilterList'] = $request->get('boolFilterList');
        }
        if ($request->get('filterList')) {
            $params['filterList'] = $request->get('filterList');
        }

        if ($request->get('select') && is_string($request->get('select'))) {
            $params['select'] = explode(',', $request->get('select'));
        }
    }

    protected function getRecordService(?string $name = null)
    {
        if (empty($name)) {
            $name = $this->name;
        }

        if ($this->getServiceFactory()->checkExists($name)) {
            $service = $this->getServiceFactory()->create($name);
        } else {
            $service = $this->getServiceFactory()->create($this->defaultRecordServiceName);
            $service->setEntityType($name);
        }

        return $service;
    }
}
