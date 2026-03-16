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

namespace Atro\Core;

use Atro\Core\Container\ServiceManagerConfig;
use Atro\Entities\User;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;

final class Container implements ContainerInterface
{
    private ?ServiceManager $sm = null;
    private ServiceManagerConfig $smConfig;

    public function __construct(ServiceManagerConfig $smConfig)
    {
        $this->smConfig = $smConfig;
    }

    /**
     * Called by Application after the ServiceManager is fully configured.
     * Two-phase init: Container is created first (so it can be passed to ContainerAbstractFactory
     * and registered as SM creationContext), then SM is injected here.
     */
    public function setSm(ServiceManager $sm): void
    {
        $this->sm = $sm;
    }

    /**
     * Register a short alias → FQCN mapping so the SM can lazily create that service.
     *
     * @deprecated Prefer registering services directly in the ServiceManager. This method
     *             remains for backwards-compatible module registration via AbstractModule::onLoad().
     */
    public function setClassAlias(string $alias, string $className): void
    {
        $this->smConfig->addClassAlias($alias, $className);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return T|mixed
     */
    public function get(string $id): mixed
    {
        return $this->sm->get($id);
    }

    public function has(string $id): bool
    {
        return $this->sm->has($id);
    }

    public function setUser(User $user): void
    {
        $this->get(UserContext::class)->set($user);
    }
}
