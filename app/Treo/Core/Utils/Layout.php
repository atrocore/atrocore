<?php

declare(strict_types=1);

namespace Treo\Core\Utils;

use Espo\Core\Utils\Json;

/**
 * Class of Layout
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class Layout extends \Espo\Core\Utils\Layout
{
    use \Treo\Traits\ContainerTrait;

    /**
     * Get Layout context
     *
     * @param string $scope
     * @param string $name
     *
     * @return json|string
     */
    public function get($scope, $name)
    {
        // prepare scope
        $scope = $this->sanitizeInput($scope);

        // prepare name
        $name = $this->sanitizeInput($name);

        // cache
        if (isset($this->changedData[$scope][$name])) {
            return Json::encode($this->changedData[$scope][$name]);
        }

        // compose
        $layout = $this->compose($scope, $name);

        // remove fields from layout if this fields not exist in metadata
        $layout = $this->disableNotExistingFields($scope, $name, $layout);

        return Json::encode($layout);
    }

    /**
     * @param string $scope
     * @param string $name
     *
     * @return array
     */
    protected function compose(string $scope, string $name): array
    {
        // from custom data
        $fileFullPath = $this->concatPath($this->getLayoutPath($scope, true), $name . '.json');
        if (file_exists($fileFullPath)) {
            return Json::decode($this->getFileManager()->getContents($fileFullPath), true);
        }

        // prepare data
        $data = [];

        // from treo core data
        $filePath = $this->concatPath(CORE_PATH . '/Treo/Resources/layouts', $scope);
        $fileFullPath = $this->concatPath($filePath, $name . '.json');
        if (file_exists($fileFullPath)) {
            // get file data
            $fileData = $this->getFileManager()->getContents($fileFullPath);

            // prepare data
            $data = Json::decode($fileData, true);
        }

        // from espo core data
        if (empty($data)) {
            $filePath = $this->concatPath(CORE_PATH . '/Espo/Resources/layouts', $scope);
            $fileFullPath = $this->concatPath($filePath, $name . '.json');
            if (file_exists($fileFullPath)) {
                // get file data
                $fileData = $this->getFileManager()->getContents($fileFullPath);

                // prepare data
                $data = Json::decode($fileData, true);
            }
        }

        // from modules data
        foreach ($this->getMetadata()->getModules() as $module) {
            $module->loadLayouts($scope, $name, $data);
        }

        // default
        if (empty($data)) {
            // prepare file path
            $fileFullPath = $this->concatPath(
                $this->concatPath($this->params['defaultsPath'], 'layouts'),
                $name . '.json'
            );

            if (file_exists($fileFullPath)) {
                // get file data
                $fileData = $this->getFileManager()->getContents($fileFullPath);

                // prepare data
                $data = Json::decode($fileData, true);
            }
        }

        return $data;
    }

    /**
     * Disable fields from layout if this fields not exist in metadata
     *
     * @param string $scope
     * @param string $name
     * @param array  $data
     *
     * @return array
     */
    protected function disableNotExistingFields($scope, $name, $data): array
    {
        // get entityDefs
        $entityDefs = $this->getMetadata()->get('entityDefs')[$scope] ?? [];

        // check if entityDefs exists
        if (!empty($entityDefs)) {
            // get fields for entity
            $fields = array_keys($entityDefs['fields']);

            // remove fields from layout if this fields not exist in metadata
            switch ($name) {
                case 'filters':
                case 'massUpdate':
                    $data = array_values(array_intersect($data, $fields));

                    break;
                case 'detail':
                case 'detailSmall':
                    foreach ($data[0]['rows'] as $key => $row) {
                        foreach ($row as $fieldKey => $fieldData) {
                            if (isset($fieldData['name']) && !in_array($fieldData['name'], $fields)) {
                                $data[0]['rows'][$key][$fieldKey] = false;
                            }
                        }
                    }

                    break;
                case 'list':
                case 'listSmall':
                    foreach ($data as $key => $row) {
                        if (isset($row['name']) && !in_array($row['name'], $fields)) {
                            array_splice($data, $key, 1);
                        }
                    }

                    break;
            }
        }

        return $data;
    }

    /**
     * @return bool
     */
    protected function isPortal(): bool
    {
        return !empty($this->getContainer()->get('portal'));
    }

    /**
     * Get a full path of the file
     *
     * @param string | array $folderPath - Folder path, Ex. myfolder
     * @param string         $filePath   - File path, Ex. file.json
     *
     * @return string
     */
    public function concatPath($folderPath, $filePath = null)
    {
        // for portal
        if ($this->isPortal()) {
            $portalPath = Util::concatPath($folderPath, 'portal/' . $filePath);
            if (file_exists($portalPath)) {
                return $portalPath;
            }
        }

        return Util::concatPath($folderPath, $filePath);
    }
}
