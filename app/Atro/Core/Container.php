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

use Atro\Core\EventManager\Manager as EventManager;
use Atro\Core\ModuleManager\Manager as ModuleManager;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Metadata;
use Atro\Entities\User;
use Doctrine\DBAL\Connection;
use Espo\ORM\EntityManager;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;

final class Container implements ContainerInterface
{
    private ServiceManager $sm;

    private array $classAliases
        = [
            'userContext'              => \Atro\Core\UserContext::class,
            'route'                    => \Atro\Core\Factories\RouteFactory::class,
            'fileManager'              => \Atro\Core\Utils\FileManager::class,
            'localStorage'             => \Atro\Core\FileStorage\LocalStorage::class,
            'consoleManager'           => \Atro\Core\ConsoleManager::class,
            'migration'                => \Atro\Core\Migration\Migration::class,
            'twig'                     => \Atro\Core\Twig\Twig::class,
            'pseudoTransactionManager' => \Atro\Core\PseudoTransactionManager::class,
            'connectionFactory'        => \Atro\Core\Factories\ConnectionFactory::class,
            'eventManager'             => \Atro\Core\Factories\EventManager::class,
            'dbal'                     => \Atro\Core\Factories\DbalConnection::class,
            'memoryStorage'            => \Atro\Core\KeyValueStorages\MemoryStorage::class,
            'memcachedStorage'         => \Atro\Core\Factories\MemcachedStorage::class,
            'log'                      => \Atro\Core\Factories\Log::class,
            'mailSender'               => \Atro\Core\Mail\Sender::class,
            'pdo'                      => \Atro\Core\Factories\Pdo::class,
            'controllerManager'        => \Atro\Core\ControllerManager::class,
            'slim'                     => \Atro\Core\Slim\Slim::class,
            'language'                 => \Atro\Core\Utils\Language::class,
            'baseLanguage'             => \Atro\Core\Utils\Language::class,
            'defaultLanguage'          => \Atro\Core\Factories\DefaultLanguage::class,
            'config'                   => \Atro\Core\Utils\Config::class,
            'htmlSanitizer'            => \Atro\Core\Utils\HTMLSanitizer::class,
            'actionManager'            => \Atro\Core\ActionManager::class,
            'fieldManager'             => \Atro\Core\Utils\FieldManager::class,
            'idGenerator'              => \Atro\Core\Utils\IdGenerator::class,
            'dataManager'              => \Atro\Core\DataManager::class,
            'schema'                   => \Atro\Core\Utils\Database\Schema\Schema::class,
            'themeManager'             => \Atro\Core\Factories\ThemeManager::class,
            'clientManager'            => \Atro\Core\Factories\ClientManager::class,
            'layoutManager'            => \Atro\Core\LayoutManager::class,
            'metadata'                 => \Atro\Core\Utils\Metadata::class,
            'realtimeManager'          => \Atro\Core\RealtimeManager::class,
            'seederFactory'            => \Atro\Core\SeederFactory::class,
            'condition'                => \Atro\Core\ConditionChecker::class,
            'matchingManager'          => \Atro\Core\MatchingManager::class,
            'crypt'                    => \Espo\Core\Utils\Crypt::class,
            'classParser'              => \Espo\Core\Utils\File\ClassParser::class,
            'aclManager'               => \Espo\Core\Factories\AclManager::class,
            'dateTime'                 => \Espo\Core\Factories\DateTime::class,
            'entityManager'            => \Espo\Core\Factories\EntityManager::class,
            'injectableFactory'        => \Espo\Core\Factories\InjectableFactory::class,
            'number'                   => \Espo\Core\Factories\Number::class,
            'ormMetadata'              => \Espo\Core\Factories\OrmMetadata::class,
            'output'                   => \Espo\Core\Factories\Output::class,
            'selectManagerFactory'     => \Espo\Core\SelectManagerFactory::class,
            'serviceFactory'           => \Espo\Core\ServiceFactory::class,
            'templateFileManager'      => \Espo\Core\Factories\TemplateFileManager::class,
            'internalAclManager'       => \Espo\Core\Factories\InternalAclManager::class,
        ];

    private array $aliases
        = [
            'connection'         => 'dbal',
            Connection::class    => 'dbal',
            EventManager::class  => 'eventManager',
            EntityManager::class => 'entityManager',
            'fieldManagerUtil'   => 'fieldManager',
        ];

    public function __construct()
    {
        $this->sm = new ServiceManager(
            [
                'abstract_factories' => [new ContainerAbstractFactory($this)],
                'aliases'            => $this->aliases,
                'services'           => ['container' => $this],
                'factories'          => [
                    'user' => fn($c) => $c->get(UserContext::class)->getUser(),
                ],
                'shared'             => ['user' => false],
            ],
            $this
        );

        $moduleManager = new ModuleManager($this);
        $this->sm->setService('moduleManager', $moduleManager);
        foreach ($moduleManager->getModules() as $module) {
            $module->onLoad();
        }
    }

    /**
     * Resolve a service name to its implementing class name.
     * Used by ContainerAbstractFactory.
     */
    public function resolveClass(string $name): string
    {
        return $this->classAliases[$name] ?? $name;
    }

    public function setClassAlias(string $alias, string $className): void
    {
        $this->classAliases[$alias] = $className;
    }

    public function get(string $id): mixed
    {
        if ($id === 'user') {
            return $this->sm->get(UserContext::class)->getUser();
        }

        if ($id === 'acl') {
            $user = $this->sm->get(UserContext::class)->getUser();
            if ($user === null) {
                throw new Exceptions\Error("ACL requires an authenticated user");
            }
            return new \Espo\Core\Acl($this->sm->get('aclManager'), $user);
        }

        return $this->sm->get($id);
    }

    public function has(string $id): bool
    {
        if ($id === 'user' || $id === 'acl') {
            return true;
        }
        return $this->sm->has($id);
    }

    /**
     * Force re-creation of a cached service on the next get() call.
     * Pass the canonical service name (not an alias).
     */
    public function reload(string $name): self
    {
        $fresh = $this->sm->build($name);
        $this->sm->setAllowOverride(true);
        $this->sm->setService($name, $fresh);
        $this->sm->setAllowOverride(false);

        return $this;
    }

    public function getDbal(): Connection
    {
        return $this->get('dbal');
    }

    public function getEntityManager(): EntityManager
    {
        return $this->get('entityManager');
    }

    public function getConfig(): Config
    {
        return $this->get('config');
    }

    public function getMetadata(): Metadata
    {
        return $this->get('metadata');
    }

    public function getUser(): User
    {
        return $this->sm->get(UserContext::class)->getUser();
    }

    public function getDataManager(): DataManager
    {
        return $this->get('dataManager');
    }

    public function getLanguage(): Language
    {
        return $this->get('language');
    }
}
