<?php

namespace Espo\Core\Controllers;
use \Treo\Core\Container;
use \Espo\Core\ServiceFactory;
use \Espo\Core\Utils\Util;

abstract class Base
{
    protected $name;

    private $container;

    private $requestMethod;

    public static $defaultAction = 'index';

    public function __construct(Container $container, $requestMethod = null)
    {
        $this->container = $container;

        if (isset($requestMethod)) {
            $this->setRequestMethod($requestMethod);
        }

        if (empty($this->name)) {
            $name = get_class($this);
            if (preg_match('@\\\\([\w]+)$@', $name, $matches)) {
                $name = $matches[1];
            }
            $this->name = $name;
        }

        $this->checkControllerAccess();
    }

    protected function checkControllerAccess()
    {
        return;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    /**
     * Get request method name (Uppercase)
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return $this->requestMethod;
    }

    protected function setRequestMethod($requestMethod)
    {
        $this->requestMethod = strtoupper($requestMethod);
    }

    protected function getUser()
    {
        return $this->container->get('user');
    }

    protected function getAcl()
    {
        return $this->container->get('acl');
    }

    protected function getAclManager()
    {
        return $this->container->get('aclManager');
    }

    protected function getConfig()
    {
        return $this->container->get('config');
    }

    protected function getPreferences()
    {
        return $this->container->get('preferences');
    }

    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    protected function getServiceFactory()
    {
        return $this->container->get('serviceFactory');
    }

    protected function getService($name)
    {
        return $this->getServiceFactory()->create($name);
    }
}

