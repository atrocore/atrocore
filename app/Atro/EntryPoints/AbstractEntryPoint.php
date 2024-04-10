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

namespace Atro\EntryPoints;

use Atro\Core\Container;
use Espo\Core\Acl;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\ClientManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\File\Manager;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

abstract class AbstractEntryPoint
{
    protected Container $container;

    public static bool $authRequired = true;
    public static bool $notStrictAuth = false;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    protected function getUser(): User
    {
        return $this->container->get('user');
    }

    protected function getAcl(): Acl
    {
        return $this->container->get('acl');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getFileManager(): Manager
    {
        return $this->container->get('fileManager');
    }

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }

    protected function getClientManager(): ClientManager
    {
        return $this->container->get('clientManager');
    }
}
