<?php

declare(strict_types=1);

namespace Treo\Core\Utils;

use Espo\Core\Utils\FieldManager as EspoFieldManager;
use Espo\Core\Utils\Metadata\Helper as MetadataHelper;
use Espo\Core\Utils\FieldManager\Hooks\Base as BaseHook;
use Treo\Traits\ContainerTrait;

/**
 * FieldManager util
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class FieldManager extends EspoFieldManager
{
    use ContainerTrait;

    /**
     *
     * @var MetadataHelper
     */
    protected $metadataHelper = null;

    /**
     * Construct
     */
    public function __construct()
    {
        // blocking parent construct
    }

    /**
     * Get attribute list by type
     *
     * @param string $scope
     * @param string $name
     * @param string $type
     *
     * @return array
     */
    protected function getAttributeListByType(string $scope, string $name, string $type): array
    {
        $fieldType = $this->getMetadata()->get('entityDefs.' . $scope . '.fields.' . $name . '.type');

        if (!$fieldType) {
            return [];
        }

        $defs = $this->getMetadata()->get('fields.' . $fieldType);
        if (!$defs) {
            return [];
        }

        if (is_object($defs)) {
            $defs = get_object_vars($defs);
        }

        $fieldList = [];
        if (isset($defs[$type . 'Fields'])) {
            $list = $defs[$type . 'Fields'];
            $naming = 'suffix';
            if (isset($defs['naming'])) {
                $naming = $defs['naming'];
            }
            if ($naming == 'prefix') {
                foreach ($list as $f) {
                    $fieldList[] = $f . ucfirst($name);
                }
            } else {
                foreach ($list as $f) {
                    $fieldList[] = $name . ucfirst($f);
                }
            }
        } else {
            if ($type == 'actual') {
                $fieldList[] = $name;
            }
        }

        return $fieldList;
    }

    /**
     * Get actual attribute list
     *
     * @param string $scope
     * @param string $name
     *
     * @return array
     */
    public function getActualAttributeList($scope, $name)
    {
        return $this->getAttributeListByType($scope, $name, 'actual');
    }

    /**
     * Get not actual attribute list
     *
     * @param string $scope
     * @param string $name
     *
     * @return array
     */
    public function getNotActualAttributeList($scope, $name)
    {
        return $this->getAttributeListByType($scope, $name, 'notActual');
    }

    /**
     * Get attribute list
     *
     * @param string $scope
     * @param string $name
     *
     * @return array
     */
    public function getAttributeList($scope, $name)
    {
        // prepare data
        $actualAttributeList = $this->getActualAttributeList($scope, $name);
        $notActualAttributeList = $this->getNotActualAttributeList($scope, $name);

        return array_merge($actualAttributeList, $notActualAttributeList);
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->getContainer()->get('metadata');
    }

    /**
     * Get language
     *
     * @return Language
     */
    protected function getLanguage()
    {
        return $this->getContainer()->get('language');
    }

    /**
     * Get base language
     *
     * @return mixed
     */
    protected function getBaseLanguage()
    {
        return $this->getContainer()->get('baseLanguage');
    }

    /**
     * Get metadata helper
     *
     * @return MetadataHelper
     */
    protected function getMetadataHelper()
    {
        if (is_null($this->metadataHelper)) {
            $this->metadataHelper = new MetadataHelper($this->getMetadata());
        }

        return $this->metadataHelper;
    }

    /**
     * Get default language
     *
     * @return Language
     */
    protected function getDefaultLanguage()
    {
        return $this->getContainer()->get('defaultLanguage');
    }

    /**
     * Get hook for fields
     *
     * @param $type
     *
     * @return BaseHook|null
     */
    protected function getHook($type)
    {
        // prepare hook
        $hook = null;

        // get class name
        $className = $this->getMetadata()->get(['fields', $type, 'hookClassName']);

        if (!empty($className) && class_exists($className)) {
            // create hook
            $hook = new $className();

            // inject dependencies
            foreach ($hook->getDependencyList() as $name) {
                $hook->inject($name, $this->getContainer()->get($name));
            }
        }

        return $hook;
    }
}
