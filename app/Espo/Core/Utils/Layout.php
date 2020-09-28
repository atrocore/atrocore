<?php

namespace Espo\Core\Utils;

class Layout
{
    private $fileManager;

    private $metadata;

    private $user;

    protected $changedData = array();

    protected $params = array(
        'defaultsPath' => CORE_PATH . '/Espo/Core/defaults',
    );

    /**
     * @var array - path to layout files
     */
    protected $paths = array(
        'corePath' => CORE_PATH . '/Espo/Resources/layouts',
        'modulePath' => CORE_PATH . '/Espo/Modules/{*}/Resources/layouts',
        'customPath' => 'custom/Espo/Custom/Resources/layouts',
    );


    public function __construct(\Espo\Core\Utils\File\Manager $fileManager, \Espo\Core\Utils\Metadata $metadata, \Espo\Entities\User $user)
    {
        $this->fileManager = $fileManager;
        $this->metadata = $metadata;
        $this->user = $user;
    }

    protected function getFileManager()
    {
        return $this->fileManager;
    }

    protected function getMetadata()
    {
        return $this->metadata;
    }

    protected function getUser()
    {
        return $this->user;
    }

    protected function sanitizeInput($name)
    {
        return preg_replace("([\.]{2,})", '', $name);
    }

    /**
     * Get Layout context
     *
     * @param $scope
     * @param $name
     *
     * @return json
     */
    public function get($scope, $name)
    {
        $scope = $this->sanitizeInput($scope);
        $name = $this->sanitizeInput($name);

        if (isset($this->changedData[$scope][$name])) {
            return Json::encode($this->changedData[$scope][$name]);
        }

        $fileFullPath = Util::concatPath($this->getLayoutPath($scope, true), $name . '.json');
        if (!file_exists($fileFullPath)) {
            $fileFullPath = Util::concatPath($this->getLayoutPath($scope), $name . '.json');
        }

        if (!file_exists($fileFullPath)) {
            $defaultPath = $this->params['defaultsPath'];
            $fileFullPath =  Util::concatPath(Util::concatPath($defaultPath, 'layouts'), $name . '.json' );

            if (!file_exists($fileFullPath)) {
                return false;
            }
        }

        return $this->getFileManager()->getContents($fileFullPath);
    }

    /**
     * Set Layout data
     * Ex. $scope = Account, $name = detail then will be created a file layoutFolder/Account/detail.json
     *
     * @param array $data
     * @param string $scope - ex. Account
     * @param string $name - detail
     *
     * @return void
     */
    public function set($data, $scope, $name)
    {
        $scope = $this->sanitizeInput($scope);
        $name = $this->sanitizeInput($name);

        if (empty($scope) || empty($name)) {
            return false;
        }

        $this->changedData[$scope][$name] = $data;
    }

    public function resetToDefault($scope, $name)
    {
        $scope = $this->sanitizeInput($scope);
        $name = $this->sanitizeInput($name);

        $filePath = 'custom/Espo/Custom/Resources/layouts/' . $scope . '/' . $name . '.json';
        if ($this->getFileManager()->isFile($filePath)) {
            $this->getFileManager()->removeFile($filePath);
        }
        if (!empty($this->changedData[$scope]) && !empty($this->changedData[$scope][$name])) {
            unset($this->changedData[$scope][$name]);
        }
        return $this->get($scope, $name);
    }

    /**
     * Save changes
     *
     * @return bool
     */
    public function save()
    {
        $result = true;

        if (!empty($this->changedData)) {
            foreach ($this->changedData as $scope => $rowData) {
                foreach ($rowData as $layoutName => $layoutData) {
                    if (empty($scope) || empty($layoutName)) {
                        continue;
                    }
                    $layoutPath = $this->getLayoutPath($scope, true);
                    $data = Json::encode($layoutData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                    $result &= $this->getFileManager()->putContents(array($layoutPath, $layoutName.'.json'), $data);
                }
            }
        }

        if ($result == true) {
            $this->clearChanges();
        }

        return (bool) $result;
    }

    /**
     * Clear unsaved changes
     *
     * @return void
     */
    public function clearChanges()
    {
        $this->changedData = array();
    }

    /**
     * Merge layout data
     * Ex. $scope= Account, $name= detail then will be created a file layoutFolder/Account/detail.json
     *
     * @param JSON string $data
     * @param string $scope - ex. Account
     * @param string $name - detail
     *
     * @return bool
     */
    public function merge($data, $scope, $name)
    {
        $scope = $this->sanitizeInput($scope);
        $name = $this->sanitizeInput($name);

        $prevData = $this->get($scope, $name);

        $prevDataArray = Json::getArrayData($prevData);
        $dataArray = Json::getArrayData($data);

        $data = Util::merge($prevDataArray, $dataArray);
        $data = Json::encode($data);

        return $this->set($data, $scope, $name);
    }

    /**
     * Get Layout path, ex. application/Modules/Crm/Layouts/Account
     *
     * @param string $entityType
     * @param bool $isCustom - if need to check custom folder
     *
     * @return string
     */
    protected function getLayoutPath($entityType, $isCustom = false)
    {
        $path = $this->paths['customPath'];

        if (!$isCustom) {
            $moduleName = $this->getMetadata()->getScopeModuleName($entityType);

            $path = $this->paths['corePath'];
            if ($moduleName !== false) {
                $path = str_replace('{*}', $moduleName, $this->paths['modulePath']);
            }
        }

        $path = Util::concatPath($path, $entityType);

        return $path;
    }
}
