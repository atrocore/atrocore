<?php

namespace Espo\Core\Utils\Database\Orm;

use Espo\Core\Utils\Util;

class RelationManager
{
    private $metadata;

    public function __construct(\Espo\Core\Utils\Metadata $metadata)
    {
        $this->metadata = $metadata;
    }

    protected function getMetadata()
    {
        return $this->metadata;
    }

    public function getLinkEntityName($entityName, $linkParams)
    {
        return isset($linkParams['entity']) ? $linkParams['entity'] : $entityName;
    }

    public function isRelationExists($relationName)
    {
        if ($this->getRelationClass($relationName) !== false) {
            return true;
        }

        return false;
    }

    protected function getRelationClass($relationName)
    {
        $relationName = ucfirst($relationName);

        $className = '\Espo\Custom\Core\Utils\Database\Orm\Relations\\'.$relationName;
        if (!class_exists($className)) {
            $className = '\Espo\Core\Utils\Database\Orm\Relations\\'.$relationName;
        }

        if (class_exists($className)) {
            return $className;
        }

        return false;
    }

    protected function isMethodExists($relationName)
    {
        $className = $this->getRelationClass($relationName);

        return method_exists($className, 'load');
    }

    /**
    * Get foreign Link
    *
    * @param string $parentLinkName
    * @param array $parentLinkParams
    * @param array $currentEntityDefs
    *
    * @return array - in format array('name', 'params')
    */
    private function getForeignLink($parentLinkName, $parentLinkParams, $currentEntityDefs)
    {
        if (isset($parentLinkParams['foreign']) && isset($currentEntityDefs['links'][$parentLinkParams['foreign']])) {
            return array(
                'name' => $parentLinkParams['foreign'],
                'params' => $currentEntityDefs['links'][$parentLinkParams['foreign']],
            );
        }

        return false;
    }

    public function convert($linkName, $linkParams, $entityName, $ormMetadata)
    {
        $entityDefs = $this->getMetadata()->get('entityDefs');

        $foreignEntityName = $this->getLinkEntityName($entityName, $linkParams);
        $foreignLink = $this->getForeignLink($linkName, $linkParams, $entityDefs[$foreignEntityName]);

        $currentType = $linkParams['type'];

        $relType = $currentType;
        if ($foreignLink !== false) {
            $relType .= '_' . $foreignLink['params']['type'];
        }
        $relType = Util::toCamelCase($relType);

        $relationName = $this->isRelationExists($relType) ? $relType /*hasManyHasMany*/ : $currentType /*hasMany*/;

        //relationDefs defined in separate file
        if (isset($linkParams['relationName'])) {
            $className = $this->getRelationClass($linkParams['relationName']);
            if (!$className) {
                $relationName = $this->isRelationExists($relType) ? $relType : $currentType;
                $className = $this->getRelationClass($relationName);
            }
        } else {
            $className = $this->getRelationClass($relationName);
        }

        if (isset($className) && $className !== false) {
            $helperClass = new $className($this->metadata, $ormMetadata, $entityDefs);
            return $helperClass->process($linkName, $entityName, $foreignLink['name'], $foreignEntityName);
        }
        //END: relationDefs defined in separate file

        return null;
    }

}