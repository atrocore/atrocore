<?php

declare(strict_types=1);

namespace Treo\Core;

use Treo\Core\Utils\Util;
use Treo\Core\Container;

/**
 * Class SelectManagerFactory
 *
 * @author r.ratsun r.ratsun@zinitsolutions.com
 */
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
                $className = '\\Treo\\SelectManagers\\' . $normalizedName;
            }
            if (!class_exists($className)) {
                $className = '\\Espo\\SelectManagers\\' . $normalizedName;
            }
            if (!class_exists($className)) {
                $className = '\\Treo\\Core\\SelectManagers\\Base';
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
