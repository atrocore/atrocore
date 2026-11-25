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

use Espo\Core\Acl;
use Atro\Core\ServiceFactory;
use Atro\Core\Utils\ClientManager;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Metadata;
use Atro\Entities\User;
use Espo\ORM\EntityManager;
use Psr\Container\ContainerInterface;

abstract class AbstractEntryPoint
{
    protected ContainerInterface $container;

    public static bool $authRequired = true;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Whether the browser already has the content with the given ETag
     */
    protected function isNotModified(string $etag): bool
    {
        $header = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($header === '') {
            return false;
        }

        foreach (explode(',', $header) as $value) {
            $value = trim($value);
            if ($value === '*' || $value === $etag || $value === 'W/' . $etag) {
                return true;
            }
        }

        return false;
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

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }

    protected function getClientManager(): ClientManager
    {
        return $this->container->get('clientManager');
    }
}
