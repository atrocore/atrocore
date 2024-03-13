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

namespace Espo\Core\Utils\Database\Orm\Relations;

class BelongsTo extends Base
{
    protected function load($linkName, $entityName)
    {
        $linkParams = $this->getLinkParams();

        $foreignEntityName = $this->getForeignEntityName();
        $foreignLinkName = $this->getForeignLinkName();

        $index = true;
        if (!empty($linkParams['noIndex'])) {
            $index = false;
        }

        $noForeignName = false;
        if (!empty($linkParams['noForeignName'])) {
            $noForeignName = true;
        } else {
            if (!empty($linkParams['foreignName'])) {
                $foreign = $linkParams['foreignName'];
            } else {
                $foreign = $this->getForeignField('name', $foreignEntityName);
            }
        }

        if (!empty($linkParams['noJoin'])) {
            $fieldNameDefs = array(
                'type' => 'varchar',
                'notStorable' => true,
                'relation' => $linkName,
                'foreign' => $this->getForeignField('name', $foreignEntityName),
            );
        } else {
            $fieldNameDefs = array(
                'type' => 'foreign',
                'relation' => $linkName,
                'foreign' => $foreign,
                'notStorable' => false
            );
        }

        $data = array (
            $entityName => array (
                'fields' => array(
                    $linkName.'Id' => array(
                        'type' => 'foreignId',
                        'index' => $index
                    )
                ),
                'relations' => array(
                    $linkName => array(
                        'type' => 'belongsTo',
                        'entity' => $foreignEntityName,
                        'key' => $linkName.'Id',
                        'foreignKey' => 'id',
                        'foreign' => $foreignLinkName
                    )
                )
            )
        );

        if (!$noForeignName) {
            $data[$entityName]['fields'][$linkName.'Name'] = $fieldNameDefs;
        }

        $fieldDefs = $this->getEntityDefs()[$entityName]['fields'][$linkName] ?? null;
        if (!empty($fieldDefs)) {
            if (array_key_exists('notNull', $fieldDefs)) {
                $data[$entityName]['fields'][$linkName . 'Id']['notNull'] = $fieldDefs['notNull'];
            }
            if (array_key_exists('default', $fieldDefs)) {
                $data[$entityName]['fields'][$linkName . 'Id']['default'] = $fieldDefs['default'];
            }
        }

        return $data;
    }

}