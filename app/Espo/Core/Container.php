<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Espo\Core;

use Espo\Core\EventManager\Manager as EventManager;
use Espo\Core\Interfaces\Injectable;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Log\Monolog\Handler\RotatingFileHandler;
use Espo\Core\Utils\Log\Monolog\Handler\StreamHandler;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Portal;
use Espo\Entities\User;
use Monolog\ErrorHandler;
use Treo\Core\ModuleManager\Manager as ModuleManager;
use Treo\Core\Utils\File\Manager as FileManager;

/**
 * Class Container
 */
class Container
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * Container constructor.
     */
    public function __construct()
    {
        // load modules
        foreach ($this->get('moduleManager')->getModules() as $module) {
            $module->onLoad();
        }
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
        if (empty($this->data[$name])) {
            $this->load($name);
        }

        if (!isset($this->data[$name]) && class_exists($name)) {
            $this->data[$name] = new $name();
            if ($this->data[$name] instanceof Injectable) {
                foreach ($this->data[$name]->getDependencyList() as $dependency) {
                    $this->data[$name]->inject($dependency, $this->get($dependency));
                }
            }
        }

        if (isset($this->data[$name])) {
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
     * Load
     *
     * @param string $name
     *
     * @throws \ReflectionException
     */
    protected function load(string $name): void
    {
        // prepare load method
        $loadMethod = 'load' . ucfirst($name);

        if (method_exists($this, $loadMethod)) {
            $this->data[$name] = $this->$loadMethod();
        } else {
            try {
                $className = $this->get('metadata')->get('app.loaders.' . ucfirst($name), null);
            } catch (\Exception $e) {
                $className = null;
            }

            if (!is_string($className) || !class_exists($className)) {
                $className = '\Treo\Core\Loaders\\' . ucfirst($name);
            }

            if (class_exists($className)) {
                $this->data[$name] = (new $className($this))->load();
            }
        }
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

    /**
     * Load container
     *
     * @return Container
     */
    protected function loadContainer(): Container
    {
        return $this;
    }

    /**
     * Load internal ACL manager
     *
     * @return mixed
     */
    protected function loadInternalAclManager()
    {
        // get class name
        $className = $this
            ->get('metadata')
            ->get('app.serviceContainer.classNames.acl', AclManager::class);

        return new $className($this->get('container'));
    }

    /**
     * Load config
     *
     * @return Config
     */
    protected function loadConfig(): Config
    {
        return new Config($this->get('container'));
    }

    /**
     * Load metadata
     *
     * @return Metadata
     */
    protected function loadMetadata(): Metadata
    {
        return new Metadata($this->get('fileManager'), $this->get('dataManager'), $this->get('moduleManager'), $this->get('eventManager'));
    }

    /**
     * Load DataManager
     *
     * @return DataManager
     */
    protected function loadDataManager(): DataManager
    {
        return new DataManager($this);
    }

    /**
     * Load QueueManager
     *
     * @return QueueManager
     */
    protected function loadQueueManager(): QueueManager
    {
        return new QueueManager($this);
    }

    /**
     * Load Language
     *
     * @return Language
     */
    protected function loadLanguage(): Language
    {
        return new Language($this, Language::detectLanguage($this->get('config'), $this->get('preferences')));
    }

    /**
     * Load BaseLanguage
     *
     * @return Language
     */
    protected function loadBaseLanguage(): Language
    {
        return new Language($this);
    }

    /**
     * Load DefaultLanguage
     *
     * @return Language
     */
    protected function loadDefaultLanguage(): Language
    {
        return new Language($this, Language::detectLanguage($this->get('config')));
    }

    /**
     * Load Log
     *
     * @return Log
     * @throws \Exception
     */
    protected function loadLog(): Log
    {
        $config = $this->get('config');

        $path = $config->get('logger.path', 'data/logs/log.log');
        $rotation = $config->get('logger.rotation', true);

        $log = new Log('Log');
        $levelCode = $log->getLevelCode($config->get('logger.level', 'WARNING'));

        if ($rotation) {
            $maxFileNumber = $config->get('logger.maxFileNumber', 30);
            $handler = new RotatingFileHandler($path, $maxFileNumber, $levelCode);
        } else {
            $handler = new StreamHandler($path, $levelCode);
        }
        $log->pushHandler($handler);

        $errorHandler = new ErrorHandler($log);
        $errorHandler->registerExceptionHandler(null, false);
        $errorHandler->registerErrorHandler(array(), false);

        return $log;
    }

    /**
     * Load file manager
     *
     * @return FileManager
     */
    protected function loadFileManager(): FileManager
    {
        return new FileManager($this->get('config'));
    }

    /**
     * Load module manager
     *
     * @return ModuleManager
     */
    protected function loadModuleManager(): ModuleManager
    {
        return new ModuleManager($this);
    }

    /**
     * Load EventManager
     *
     * @return EventManager
     */
    protected function loadEventManager(): EventManager
    {
        $eventManager = new EventManager($this);
        $eventManager->loadListeners();

        return $eventManager;
    }

    protected function loadPseudoTransactionManager(): PseudoTransactionManager
    {
        return new PseudoTransactionManager($this);
    }

    protected function loadPdo(): \PDO
    {
        /** @var Config $config */
        $config = $this->get('config');

        $params = [
            'host'      => $config->get('database.host'),
            'port'      => $config->get('database.port'),
            'dbname'    => $config->get('database.dbname'),
            'user'      => $config->get('database.user'),
            'charset'   => $config->get('database.charset', 'utf8'),
            'password'  => $config->get('database.password'),
            'sslCA'     => $config->get('database.sslCA'),
            'sslCert'   => $config->get('database.sslCert'),
            'sslKey'    => $config->get('database.sslKey'),
            'sslCAPath' => $config->get('database.sslCAPath'),
            'sslCipher' => $config->get('database.sslCipher')
        ];

        // prepare params
        $port = empty($params['port']) ? '' : "port={$params['port']};";

        $options = [];
        if (isset($params['sslCA'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = $params['sslCA'];
        }
        if (isset($params['sslCert'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CERT] = $params['sslCert'];
        }
        if (isset($params['sslKey'])) {
            $options[\PDO::MYSQL_ATTR_SSL_KEY] = $params['sslKey'];
        }
        if (isset($params['sslCAPath'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CAPATH] = $params['sslCAPath'];
        }
        if (isset($params['sslCipher'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CIPHER] = $params['sslCipher'];
        }

        $pdo = new \PDO("mysql:host={$params['host']};{$port}dbname={$params['dbname']};charset={$params['charset']}", $params['user'], $params['password'], $options);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
