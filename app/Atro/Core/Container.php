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
use Atro\Core\Factories\FactoryInterface as Factory;
use Atro\Core\ModuleManager\Manager as ModuleManager;
use Doctrine\DBAL\Connection;
use Espo\Core\Interfaces\Injectable;
use Espo\Core\Utils;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

class Container
{
    protected array $data = [];

    protected array $classAliases
        = [
            'route'                    => \Atro\Core\Factories\RouteFactory::class,
            'fileManager'              => \Atro\Core\Utils\FileManager::class,
            'localStorage'             => \Atro\Core\FileStorage\LocalStorage::class,
            'consoleManager'           => \Atro\Core\ConsoleManager::class,
            'migration'                => \Atro\Core\Migration\Migration::class,
            'twig'                     => \Atro\Core\Twig\Twig::class,
            'pseudoTransactionManager' => \Atro\Core\PseudoTransactionManager::class,
            'connectionFactory'        => \Atro\Core\Factories\ConnectionFactory::class,
            'eventManager'             => \Atro\Core\Factories\EventManager::class,
            EventManager::class        => \Atro\Core\Factories\EventManager::class,
            'connection'               => \Atro\Core\Factories\Connection::class,
            Connection::class          => \Atro\Core\Factories\Connection::class,
            'memoryStorage'            => \Atro\Core\KeyValueStorages\MemoryStorage::class,
            'memcachedStorage'         => \Atro\Core\Factories\MemcachedStorage::class,
            'log'                      => \Atro\Core\Factories\Log::class,
            'mailSender'               => \Atro\Core\Mail\Sender::class,
            'pdo'                      => \Atro\Core\Factories\Pdo::class,
            'controllerManager'        => \Atro\Core\ControllerManager::class,
            'slim'                     => \Atro\Core\Slim\Slim::class,
            'language'                 => \Atro\Core\Utils\Language::class,
            'config'                   => \Atro\Core\Utils\Config::class,
            'htmlSanitizer'            => \Atro\Core\Utils\HTMLSanitizer::class,
            'actionManager'            => \Atro\Core\ActionManager::class,
            'fieldManager'             => \Atro\Core\Utils\FieldManager::class,
            'fieldManagerUtil'         => \Atro\Core\Utils\FieldManager::class,
            'dataManager'              => \Atro\Core\DataManager::class,
            'schema'                   => \Atro\Core\Utils\Database\Schema\Schema::class,
            'styleManager'             => \Atro\Core\Factories\StyleManager::class,
            'crypt'                    => \Espo\Core\Utils\Crypt::class,
            'classParser'              => \Espo\Core\Utils\File\ClassParser::class,
            'layout'                   => \Espo\Core\Utils\Layout::class,
            'acl'                      => \Espo\Core\Factories\Acl::class,
            'aclManager'               => \Espo\Core\Factories\AclManager::class,
            'clientManager'            => \Espo\Core\Factories\ClientManager::class,
            'dateTime'                 => \Espo\Core\Factories\DateTime::class,
            'entityManager'            => \Espo\Core\Factories\EntityManager::class,
            EntityManager::class       => \Espo\Core\Factories\EntityManager::class,
            'filePathBuilder'          => \Espo\Core\Factories\FilePathBuilder::class,
            'injectableFactory'        => \Espo\Core\Factories\InjectableFactory::class,
            'number'                   => \Espo\Core\Factories\Number::class,
            'ormMetadata'              => \Espo\Core\Factories\OrmMetadata::class,
            'output'                   => \Espo\Core\Factories\Output::class,
            'preferences'              => \Espo\Core\Factories\Preferences::class,
            'selectManagerFactory'     => \Espo\Core\SelectManagerFactory::class,
            'serviceFactory'           => \Espo\Core\ServiceFactory::class,
            'templateFileManager'      => \Espo\Core\Factories\TemplateFileManager::class,
            'defaultLanguage'          => \Espo\Core\Factories\DefaultLanguage::class,
            'baseLanguage'             => \Espo\Core\Factories\BaseLanguage::class,
            Utils\Language::class      => \Espo\Core\Factories\Language::class,
            'metadata'                 => \Espo\Core\Factories\Metadata::class,
            Utils\Metadata::class      => \Espo\Core\Factories\Metadata::class,
            'internalAclManager'       => \Espo\Core\Factories\InternalAclManager::class,
        ];

    public function __construct()
    {
        $this->data['moduleManager'] = new ModuleManager($this);
        foreach ($this->data['moduleManager']->getModules() as $module) {
            $module->onLoad();
        }
    }

    public function setClassAlias(string $alias, string $className): void
    {
        $this->classAliases[$alias] = $className;
    }

    /**
     * Get class
     *
     * @param string $name
     *
     * @return mixed
     */
    public function get(string $name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        // load itself
        if ($name === 'container') {
            $this->data[$name] = $this;
            return $this->data[$name];
        }

        $className = isset($this->classAliases[$name]) ? $this->classAliases[$name] : $name;
        if (class_exists($className)) {
            if (is_a($className, Factory::class, true)) {
                $this->data[$name] = (new $className())->create($this);
                return $this->data[$name];
            }

            $reflectionClass = new \ReflectionClass($className);
            if (!empty($constructor = $reflectionClass->getConstructor()) && !empty($params = $constructor->getParameters())) {
                $input = [];
                foreach ($params as $param) {
                    $dependencyClass = $param->getType() && !$param->getType()->isBuiltin() ? new \ReflectionClass($param->getType()->getName()) : null;
                    if (!empty($dependencyClass)) {
                        if ($dependencyClass->getName() === self::class) {
                            $input[] = $this;
                        } else {
                            $input[] = $this->get($dependencyClass->getName());
                        }
                    }
                }
                $this->data[$name] = new $className(...$input);
                return $this->data[$name];
            }

            if (is_a($className, Injectable::class, true)) {
                $this->data[$name] = new $className();
                foreach ($this->data[$name]->getDependencyList() as $dependency) {
                    $this->data[$name]->inject($dependency, $this->get($dependency));
                }
                return $this->data[$name];
            }

            $this->data[$name] = new $className();

            return $this->data[$name];
        }

        return null;
    }

    /**
     * Set User
     *
     * @param User $user
     */
    public function setUser(User $user): Container
    {
        $this->set('user', $user);

        return $this;
    }

    /**
     * Set class
     */
    protected function set($name, $obj)
    {
        $this->data[$name] = $obj;
    }

    /**
     * Reload object
     *
     * @param string $name
     *
     * @return Container
     */
    public function reload(string $name): Container
    {
        // unset
        if (isset($this->data[$name])) {
            unset($this->data[$name]);
        }

        return $this;
    }
}
