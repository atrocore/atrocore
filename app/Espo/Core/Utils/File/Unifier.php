<?php

namespace Espo\Core\Utils\File;

use Espo\Core\Utils;

class Unifier
{
    private $fileManager;

    private $metadata;

    protected $useObjects;

    protected $unsetFileName = 'unset.json';

    protected $pathToDefaults = CORE_PATH . '/Espo/Core/defaults';

    public function __construct(\Espo\Core\Utils\File\Manager $fileManager, \Espo\Core\Utils\Metadata $metadata = null, $useObjects = false)
    {
        $this->fileManager = $fileManager;
        $this->metadata = $metadata;
        $this->useObjects = $useObjects;
    }

    protected function getFileManager()
    {
        return $this->fileManager;
    }

    protected function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Unite file content to the file
     *
     * @param  string  $name
     * @param  array|string  $paths
     * @param  boolean $recursively Note: only for first level of sub directory, other levels of sub directories will be ignored
     *
     * @return mixed
     */
    public function unify($name, $paths, $recursively = false)
    {
        if (is_string($paths)) {
            return $this->unifySingle($paths, $name, $recursively);
        }

        $content = $this->unifySingle($paths['corePath'], $name, $recursively);

        if (!empty($paths['customPath'])) {
            if ($this->useObjects) {
                $content = Utils\DataUtil::merge($content, $this->unifySingle($paths['customPath'], $name, $recursively));
            } else {
                $content = Utils\Util::merge($content, $this->unifySingle($paths['customPath'], $name, $recursively));
            }
        }

        return $content;
    }

    /**
     * Unite file content to the file for one directory [NOW ONLY FOR METADATA, NEED TO CHECK FOR LAYOUTS AND OTHERS]
     *
     * @param string $dirPath
     * @param string $type - name of type array("metadata", "layouts"), ex. $this->name
     * @param bool $recursively - Note: only for first level of sub directory, other levels of sub directories will be ignored
     * @param string $moduleName - name of module if exists
     *
     * @return string - content of the files
     */
    protected function unifySingle($dirPath, $type, $recursively = false, $moduleName = '')
    {
        $content = [];
        $unsets = [];

        if ($this->useObjects) {
            $content = (object) [];
            $unsets = (object) [];
        }

        if (empty($dirPath) || !file_exists($dirPath)) {
            return $content;
        }

        $fileList = $this->getFileManager()->getFileList($dirPath, $recursively, '\.json$');

        $dirName = $this->getFileManager()->getDirName($dirPath, false);
        $defaultValues = $this->loadDefaultValues($dirName, $type);

        foreach ($fileList as $dirName => $fileName) {
            if (is_array($fileName)) { /*only first level of a sub directory*/
                if ($this->useObjects) {
                    $content->$dirName = $this->unifySingle(Utils\Util::concatPath($dirPath, $dirName), $type, false, $moduleName);
                } else {
                    $content[$dirName] = $this->unifySingle(Utils\Util::concatPath($dirPath, $dirName), $type, false, $moduleName);
                }

            } else {
                if ($fileName === $this->unsetFileName) {
                    $fileContent = $this->getFileManager()->getContents(array($dirPath, $fileName));
                    if ($this->useObjects) {
                        $unsets = Utils\Json::decode($fileContent);
                    } else {
                        $unsets = Utils\Json::getArrayData($fileContent);
                    }
                    continue;
                }

                $mergedValues = $this->unifyGetContents(array($dirPath, $fileName), $defaultValues);

                if (!empty($mergedValues)) {
                    $name = $this->getFileManager()->getFileName($fileName, '.json');

                    if ($this->useObjects) {
                        $content->$name = $mergedValues;
                    } else {
                        $content[$name] = $mergedValues;
                    }

                }
            }
        }

        if ($this->useObjects) {
            $content = Utils\DataUtil::unsetByKey($content, $unsets);
        } else {
            $content = Utils\Util::unsetInArray($content, $unsets);
        }

        return $content;
    }

    /**
     * Helpful method for get content from files for unite Files
     *
     * @param string | array $paths
     * @param string | array() $defaults - It can be a string like ["metadata","layouts"] OR an array with default values
     *
     * @return array
     */
    protected function unifyGetContents($paths, $defaults)
    {
        $fileContent = $this->getFileManager()->getContents($paths);

        if ($this->useObjects) {
            $decoded = Utils\Json::decode($fileContent);
        } else {
            $decoded = Utils\Json::getArrayData($fileContent, null);
        }

        if (!isset($decoded)) {
            $GLOBALS['log']->emergency('Syntax error in '.Utils\Util::concatPath($paths));
            if ($this->useObjects) {
                return (object) [];
            } else {
                return array();
            }
        }

        return $decoded;
    }

    /**
     * Load default values for selected type [metadata, layouts]
     *
     * @param string $name
     * @param string $type - [metadata, layouts]
     *
     * @return array
     */
    protected function loadDefaultValues($name, $type = 'metadata')
    {
        $defaultValue = $this->getFileManager()->getContents(array($this->pathToDefaults, $type, $name.'.json') );
        if ($defaultValue !== false) {
            if ($this->useObjects) {
                return Utils\Json::decode($defaultValue);
            } else {
                return Utils\Json::decode($defaultValue, true);
            }
        }
        if ($this->useObjects) {
            return (object) [];
        } else {
            return array();
        }
    }

}

?>