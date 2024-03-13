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

namespace Atro\Core\ModuleManager;

use Espo\Core\Utils\DataUtil;
use Espo\Core\Utils\File\Unifier;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Route;
use Atro\Core\Container;
use Espo\Core\Utils\Util;

abstract class AbstractModule
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $package;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Unifier
     */
    protected $unifier;

    /**
     * @var Unifier
     */
    protected $objUnifier;

    /**
     * @var null
     */
    private $routeUtil = null;

    /**
     * Get module load order
     *
     * @return int
     */
    abstract public static function getLoadOrder(): int;

    /**
     * AbstractModule constructor.
     *
     * @param string    $id
     * @param string    $path
     * @param array     $package
     * @param Container $container
     */
    public function __construct(
        string $id,
        string $path,
        array $package,
        Container $container
    ) {
        $this->id = $id;
        $this->path = $path;
        $this->package = $package;
        $this->container = $container;
    }

    /**
     * Get client path
     *
     * @return string
     */
    public function getClientPath(): string
    {
        return $this->path . 'client/';
    }

    /**
     * @return bool
     */
    public function isSystem(): bool
    {
        return false;
    }

    public function prepareApiDocs(array &$data, array $schemas): void
    {
    }

    /**
     * @return string
     */
    public function getComposerName(): string
    {
        return (!empty($this->package['name'])) ? (string)$this->package['name'] : "-";
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        if (!empty($this->package['extra']['name']['default'])) {
            return (string)$this->package['extra']['name']['default'];
        }

        return "";
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        if (!empty($this->package['extra']['description']['default'])) {
            return (string)$this->package['extra']['description']['default'];
        }

        return "";
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return (!empty($this->package['version'])) ? $this->package['version'] : "";
    }

    /**
     * Load module services
     *
     * @return array
     */
    public function loadServices(): array
    {
        // prepare result
        $result = [];

        // prepare path
        $path = $this->getAppPath() . 'Services';

        if (is_dir($path)) {
            foreach (scandir($path) as $item) {
                if (preg_match_all('/^(.*)\.php$/', $item, $matches)) {
                    $result[$matches[1][0]] = "\\" . $this->id . "\\Services\\" . $matches[1][0];
                }
            }
        }

        return $result;
    }

    /**
     * Load module metadata
     *
     * @param \stdClass $data
     */
    public function loadMetadata(\stdClass &$data)
    {
        $metadata = $this
            ->getObjUnifier()
            ->unify('metadata', $this->getAppPath() . 'Resources/metadata', true);
        $data = DataUtil::merge($data, $metadata);
    }

    /**
     * Load module layouts
     *
     * @param string $scope
     * @param string $name
     * @param array  $data
     */
    public function loadLayouts(string $scope, string $name, array &$data)
    {
        // load layout class
        $layout = $this->container->get('layout');

        // prepare file path
        $filePath = $layout->concatPath($this->getAppPath() . 'Resources/layouts', $scope);
        $fileFullPath = $layout->concatPath($filePath, $name . '.json');

        if (file_exists($fileFullPath)) {
            // get file data
            $fileData = $this->container->get('fileManager')->getContents($fileFullPath);

            // prepare data
            $data = array_merge_recursive($data, Json::decode($fileData, true));
        }
    }

    /**
     * Load module routes
     *
     * @param array $data
     */
    public function loadRoutes(array &$data)
    {
        $data = $this->getRouteUtil()->getAddData($data, $this->getAppPath() . 'Resources/routes.json');
    }

    /**
     * Load module listeners
     *
     * @param array $listeners
     */
    public function loadListeners(array &$listeners)
    {
        // prepare path
        $dirPath = $this->getAppPath() . 'Listeners';

        if (file_exists($dirPath) && is_dir($dirPath)) {
            foreach (scandir($dirPath) as $file) {
                if (!in_array($file, ['.', '..'])) {
                    // prepare name
                    $name = str_replace(".php", "", $file);

                    // push
                    $listeners[$name][] = "\\" . $this->id . "\\Listeners\\" . $name;
                }
            }
        }
    }

    /**
     * Load module translates
     *
     * @param array $data
     */
    public function loadTranslates(array &$data)
    {
        $data = Util::merge($data, $this->getUnifier()->unify('i18n', $this->getAppPath() . 'Resources/i18n', true));
    }

    /**
     * Get className hash
     *
     * @param string $classesDir
     *
     * @return array
     */
    public function getClassNameHash(string $classesDir): array
    {
        // get files
        $fileList = $this
            ->container
            ->get('fileManager')
            ->getFileList($this->getAppPath() . $classesDir, false, '\.php$', true);

        $result = [];
        if (!empty($fileList)) {
            foreach ($fileList as $item) {
                // prepare classname
                $className = str_replace('.php', '', $item);

                $result[$className] = "\\" . $this->id . "\\$classesDir\\$className";
            }
        }

        return $result;
    }

    /**
     * On module load
     */
    public function onLoad()
    {
    }

    /**
     * @return Unifier
     */
    protected function getUnifier(): Unifier
    {
        if (!isset($this->unifier)) {
            $this->unifier = new Unifier(
                $this->container->get('fileManager'),
                $this->container->get('metadata'),
                false
            );
        }

        return $this->unifier;
    }

    /**
     * @return Unifier
     */
    protected function getObjUnifier(): Unifier
    {
        if (!isset($this->objUnifier)) {
            $this->objUnifier = new Unifier(
                $this->container->get('fileManager'),
                $this->container->get('metadata'),
                true
            );
        }

        return $this->objUnifier;
    }

    /**
     * @return Route
     */
    protected function getRouteUtil(): Route
    {
        if (is_null($this->routeUtil)) {
            $this->routeUtil = new Route($this->container->get('fileManager'), $this->container->get('moduleManager'), $this->container->get('dataManager'));
        }

        return $this->routeUtil;
    }

    /**
     * @return string
     */
    public function getAppPath(): string
    {
        return $this->path . 'app/';
    }
}
