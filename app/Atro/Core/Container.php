<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core;

use Atro\Core\EventManager\Manager as EventManager;
use Atro\Core\Factories\FactoryInterface as Factory;
use Espo\Core\Interfaces\Injectable;
use Espo\Entities\Portal;
use Espo\Entities\User;
use Atro\Core\ModuleManager\Manager as ModuleManager;
use Espo\ORM\EntityManager;
use Doctrine\DBAL\Connection;
use Espo\Core\Utils;

class Container
{
    protected array $data = [];

    protected array $classAliases
        = [
            'consoleManager'           => \Atro\Core\ConsoleManager::class,
            'thumbnail'                => \Atro\Core\Thumbnail\Image::class,
            'migration'                => \Atro\Core\Migration\Migration::class,
            'twig'                     => \Atro\Core\Twig\Twig::class,
            'queueManager'             => \Atro\Core\QueueManager::class,
            'pseudoTransactionManager' => \Atro\Core\PseudoTransactionManager::class,
            'eventManager'             => \Atro\Core\Factories\EventManager::class,
            EventManager::class        => \Atro\Core\Factories\EventManager::class,
            'connection'               => \Atro\Core\Factories\Connection::class,
            Connection::class          => \Atro\Core\Factories\Connection::class,
            'crypt'                    => \Espo\Core\Utils\Crypt::class,
            'cronManager'              => \Espo\Core\CronManager::class,
            'slim'                     => \Espo\Core\Utils\Api\Slim::class,
            'classParser'              => \Espo\Core\Utils\File\ClassParser::class,
            'fieldManager'             => \Espo\Core\Utils\FieldManager::class,
            'layout'                   => \Espo\Core\Utils\Layout::class,
            'acl'                      => \Espo\Core\Factories\Acl::class,
            'aclManager'               => \Espo\Core\Factories\AclManager::class,
            'clientManager'            => \Espo\Core\Factories\ClientManager::class,
            'controllerManager'        => \Espo\Core\ControllerManager::class,
            'dateTime'                 => \Espo\Core\Factories\DateTime::class,
            'entityManager'            => \Espo\Core\Factories\EntityManager::class,
            EntityManager::class       => \Espo\Core\Factories\EntityManager::class,
            'entityManagerUtil'        => \Espo\Core\Factories\EntityManagerUtil::class,
            'fieldManagerUtil'         => \Espo\Core\Factories\FieldManagerUtil::class,
            'workflow'                 => \Espo\Core\Factories\Workflow::class,
            'filePathBuilder'          => \Espo\Core\Factories\FilePathBuilder::class,
            'fileStorageManager'       => \Espo\Core\Factories\FileStorageManager::class,
            'injectableFactory'        => \Espo\Core\Factories\InjectableFactory::class,
            'mailSender'               => \Espo\Core\Factories\MailSender::class,
            'number'                   => \Espo\Core\Factories\Number::class,
            'ormMetadata'              => \Espo\Core\Factories\OrmMetadata::class,
            'output'                   => \Espo\Core\Factories\Output::class,
            'preferences'              => \Espo\Core\Factories\Preferences::class,
            'scheduledJob'             => \Espo\Core\Utils\ScheduledJob::class,
            'schema'                   => \Espo\Core\Factories\Schema::class,
            'selectManagerFactory'     => \Espo\Core\SelectManagerFactory::class,
            'serviceFactory'           => \Espo\Core\ServiceFactory::class,
            'templateFileManager'      => \Espo\Core\Factories\TemplateFileManager::class,
            'themeManager'             => \Espo\Core\Factories\ThemeManager::class,
            'pdo'                      => \Espo\Core\Factories\Pdo::class,
            'fileManager'              => \Espo\Core\Factories\FileManager::class,
            'log'                      => \Espo\Core\Factories\Log::class,
            'defaultLanguage'          => \Espo\Core\Factories\DefaultLanguage::class,
            'baseLanguage'             => \Espo\Core\Factories\BaseLanguage::class,
            'language'                 => \Espo\Core\Factories\Language::class,
            Utils\Language::class      => \Espo\Core\Factories\Language::class,
            'dataManager'              => \Espo\Core\Factories\DataManager::class,
            'metadata'                 => \Espo\Core\Factories\Metadata::class,
            Utils\Metadata::class      => \Espo\Core\Factories\Metadata::class,
            'config'                   => \Espo\Core\Utils\Config::class,
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
     * Set portal
     *
     * @param Portal $portal
     *
     * @return Container
     */
    public function setPortal(Portal $portal): Container
    {
        $this->set('portal', $portal);

        $data = [];
        foreach ($this->get('portal')->getSettingsAttributeList() as $attribute) {
            $data[$attribute] = $this->get('portal')->get($attribute);
        }
        if (empty($data['theme'])) {
            unset($data['theme']);
        }
        if (empty($data['defaultCurrency'])) {
            unset($data['defaultCurrency']);
        }

        foreach ($data as $attribute => $value) {
            $this->get('config')->set($attribute, $value, true);
        }

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
