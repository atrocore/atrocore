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

namespace Atro\Handlers;

use Atro\Core\Utils\Config;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Metadata;
use Atro\Entities\User;
use Espo\Core\Acl;
use Espo\Core\ServiceFactory;
use Espo\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

abstract class AbstractHandler implements MiddlewareInterface
{
    public function __construct(protected readonly ContainerInterface $container)
    {
    }

    protected function getAcl(): Acl
    {
        return $this->container->get('acl');
    }

    protected function getUser(): User
    {
        return $this->container->get('user');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }
}
