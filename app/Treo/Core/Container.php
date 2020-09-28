<?php

declare(strict_types=1);

namespace Treo\Core;

use Espo\Core\AclManager;
use Espo\Entities\Portal;
use Espo\Entities\User;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Log\Monolog\Handler\RotatingFileHandler;
use Espo\Core\Utils\Log\Monolog\Handler\StreamHandler;
use Monolog\ErrorHandler;
use Treo\Core\EventManager\Manager as EventManager;
use Treo\Core\ModuleManager\Manager as ModuleManager;
use Treo\Core\Utils\Config;
use Treo\Core\Utils\File\Manager as FileManager;
use Treo\Core\Utils\Metadata;

/**
 * Class Container
 *
 * @author r.ratsun@gmail.com
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
        if (empty($data['language'])) {
            unset($data['language']);
        }
        if (empty($data['theme'])) {
            unset($data['theme']);
        }
        if (empty($data['timeZone'])) {
            unset($data['timeZone']);
        }
        if (empty($data['dateFormat'])) {
            unset($data['dateFormat']);
        }
        if (empty($data['timeFormat'])) {
            unset($data['timeFormat']);
        }
        if (isset($data['weekStart']) && $data['weekStart'] === -1) {
            unset($data['weekStart']);
        }
        if (array_key_exists('weekStart', $data) && is_null($data['weekStart'])) {
            unset($data['weekStart']);
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
                $className = $this->get('metadata')->get('app.loaders.' . ucfirst($name));
            } catch (\Exception $e) {
            }

            if (!isset($className) || !class_exists($className)) {
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

        // load
        $this->load($name);

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
    protected function loadConfig()
    {
        return new Config(new FileManager());
    }

    /**
     * Load metadata
     *
     * @return Metadata
     */
    protected function loadMetadata(): Metadata
    {
        return new Metadata(
            $this->get('fileManager'),
            $this->get('moduleManager'),
            $this->get('eventManager'),
            $this->get('config')->get('useCache', false)
        );
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

        $path = $config->get('logger.path', 'data/logs/espo.log');
        $rotation = $config->get('logger.rotation', true);

        $log = new Log('Espo');
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
}
