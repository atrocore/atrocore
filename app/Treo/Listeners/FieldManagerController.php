<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Treo\Core\Utils\Util;
use Treo\Core\EventManager\Event;

/**
 * Class FieldManagerController
 */
class FieldManagerController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforePostActionCreate(Event $event)
    {
        // is default value valid ?
        $this->isDefaultValueValid($event->getArgument('data')->type, $event->getArgument('data')->default);
    }

    /**
     * @param Event $event
     */
    public function afterPostActionCreate(Event $event)
    {
        $data = Json::decode(Json::encode($event->getArgument('data')), true);

        $this->updateEnumDefaultMultilang($event->getArgument('params')['scope'], $data);
    }

    /**
     * @param Event $event
     */
    public function beforePatchActionUpdate(Event $event)
    {
        $this->beforePostActionCreate($event);
    }

    /**
     * @param Event $event
     */
    public function beforePutActionUpdate(Event $event)
    {
        $this->beforePostActionCreate($event);
    }

    /**
     * @param Event $event
     */
    public function beforeDeleteActionDelete(Event $event)
    {
        // delete columns from DB
        $this->deleteColumns($event->getArgument('params')['scope'], $event->getArgument('params')['name']);
    }

    /**
     * Delete column(s) from DB
     *
     * @param string $scope
     * @param string $field
     */
    protected function deleteColumns(string $scope, string $field): void
    {
        // get field metadata
        $fields = $this
            ->getContainer()
            ->get('metadata')
            ->getFieldList($scope, $field);

        if (!empty($fields)) {
            // prepare table name
            $table = Util::toUnderScore($scope);

            foreach ($fields as $name => $row) {
                // prepare column
                $column = Util::toUnderScore($name);
                switch ($row['type']) {
                    case 'file':
                        $column .= '_id';
                        break;
                    case 'image':
                        $column .= '_id';
                        break;
                    case 'asset':
                        $column .= '_id';
                        break;
                }

                try {
                    // execute SQL
                    $sth = $this
                        ->getEntityManager()
                        ->getPDO()
                        ->prepare("ALTER TABLE {$table} DROP COLUMN {$column};");
                    $sth->execute();
                } catch (\Exception $e) {
                }
            }
        }
    }

    /**
     * Is default value valid
     *
     * @param string $type
     * @param mixed  $default
     *
     * @return bool
     * @throws BadRequest
     */
    protected function isDefaultValueValid(string $type, $default): bool
    {
        // prepare types
        $types = ['text', 'textMultiLang', 'wysiwyg', 'wysiwygMultiLang'];

        if (in_array($type, $types) && is_string($default) && strpos($default, "'") !== false) {
            // prepare message
            $message = $this
                ->getLanguage()
                ->translate('defaultValidationFailed', 'messages', 'FieldManager');

            throw new BadRequest($message);
        }

        return true;
    }

    /**
     * @param string $entityName
     * @param array $data
     */
    protected function updateEnumDefaultMultilang(string $entityName, array $data): void
    {
        if ($data['type'] = ['enum'] && $data['isMultilang'] ?? false && $this->getConfig()->get('isMultilangActive', false)) {
            $default = $data['default'];
            $defaultPosition = array_search($default, $data['options']);

            if ($defaultPosition !== false) {
                $entityName = Util::toUnderScore($entityName);
                $fieldName = $data['name'];
                $sql = "UPDATE $entityName SET ";

                foreach ($this->getConfig()->get('inputLanguageList', []) as $key => $locale) {
                    $camelCaseLocale = Util::toCamelCase(strtolower($locale), '_', true);
                    $multilangOptions = 'options' . $camelCaseLocale;

                    if (isset($data[$multilangOptions]) && isset($data[$multilangOptions][$defaultPosition])) {
                        $multilangFieldName = $fieldName . '_' . strtolower($locale);
                        $multilangValue = $data[$multilangOptions][$defaultPosition];

                        if ($key != 0) {
                            $sql .= ",";
                        }
                        $sql .= "$multilangFieldName = '$multilangValue'";
                    }
                }

                $sth = $this
                    ->getEntityManager()
                    ->getPDO()
                    ->prepare($sql);
                $sth->execute();
            }
        }
    }
}
