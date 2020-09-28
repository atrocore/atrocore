<?php

namespace Espo\Core\Portal\Utils;

use \Espo\Core\Utils\Util;
use \Espo\Core\Utils\Json;

class Layout extends \Espo\Core\Utils\Layout
{
    public function get($scope, $name)
    {
        $scope = $this->sanitizeInput($scope);
        $name = $this->sanitizeInput($name);

        if (isset($this->changedData[$scope][$name])) {
            return Json::encode($this->changedData[$scope][$name]);
        }

        $fileFullPath = Util::concatPath($this->getLayoutPath($scope, true), 'portal/' . $name . '.json');

        if (!file_exists($fileFullPath)) {
            $fileFullPath = Util::concatPath($this->getLayoutPath($scope), 'portal/' . $name . '.json');
        }
        if (!file_exists($fileFullPath)) {
            $fileFullPath = Util::concatPath($this->getLayoutPath($scope, true), $name . '.json');
        }
        if (!file_exists($fileFullPath)) {
            $fileFullPath = Util::concatPath($this->getLayoutPath($scope), $name . '.json');
        }


        if (!file_exists($fileFullPath)) {
            $defaultPath = $this->params['defaultsPath'];
            $fileFullPath = Util::concatPath(Util::concatPath($defaultPath, 'layouts'), $name . '.json' );

            if (!file_exists($fileFullPath)) {
                return false;
            }
        }

        return $this->getFileManager()->getContents($fileFullPath);
    }


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

}
