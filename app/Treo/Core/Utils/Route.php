<?php

declare(strict_types=1);

namespace Treo\Core\Utils;

use Espo\Core\Utils\Route as Base;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\File\Manager as FileManager;
use Treo\Core\ModuleManager\Manager as ModuleManager;

/**
 * Class Route
 *
 * @author r.ratsun r.ratsun@treolabs.com
 */
class Route extends Base
{
    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @inheritdoc
     */
    public function __construct(
        Config $config,
        Metadata $metadata,
        FileManager $fileManager,
        ModuleManager $moduleManager
    ) {
        // call parent
        parent::__construct($config, $metadata, $fileManager);

        $this->moduleManager = $moduleManager;
    }

    /**
     * @inheritdoc
     */
    protected function unify()
    {
        // for custom
        $data = $this->getAddData([], $this->paths['customPath']);

        // for module
        foreach ($this->getModuleManager()->getModules() as $module) {
            $module->loadRoutes($data);
        }

        // for treo core
        $data = $this->getAddData($data, CORE_PATH . '/Treo/Resources/routes.json');

        // for core
        $data = $this->getAddData($data, CORE_PATH . '/Espo/Resources/routes.json');

        return $data;
    }

    /**
     * @return ModuleManager
     */
    protected function getModuleManager(): ModuleManager
    {
        return $this->moduleManager;
    }
}
