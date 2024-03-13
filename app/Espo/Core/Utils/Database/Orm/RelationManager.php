<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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
 */

namespace Espo\Core\Utils\Database\Orm;

use Espo\Core\Utils\Util;

class RelationManager
{
    private array $entityDefs;

    public function __construct(array $entityDefs)
    {
        $this->entityDefs = $entityDefs;
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
        $className = '\Espo\Core\Utils\Database\Orm\Relations\\' . ucfirst($relationName);
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
        $entityDefs = $this->entityDefs;

        $foreignEntityName = $this->getLinkEntityName($entityName, $linkParams);

        $foreignLink = false;
        if (isset($entityDefs[$foreignEntityName])) {
            $foreignLink = $this->getForeignLink($linkName, $linkParams, $entityDefs[$foreignEntityName]);
        }

        if (empty($linkParams['type'])){
            return null;
        }

        $currentType = $linkParams['type'];

        $relType = $currentType;
        if ($foreignLink !== false && isset($foreignLink['params']['type'])) {
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
            $helperClass = new $className($ormMetadata, $entityDefs);
            $foreignLinkName = !empty($foreignLink['name']) ? $foreignLink['name'] : false;
            return $helperClass->process($linkName, $entityName, $foreignLinkName, $foreignEntityName);
        }
        //END: relationDefs defined in separate file

        return null;
    }

}