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
use Espo\Core\Exceptions\Exception;
use Espo\Core\Utils\Util;
use Treo\Core\EventManager\Event;

/**
 * Class FieldManagerController
 */
class FieldManagerController extends AbstractListener
{
    public function beforePostActionCreate(Event $event): void
    {
        $data = $event->getArgument('data');
        $params = $event->getArgument('params');

        // is default value valid ?
        $this->isDefaultValueValid($data->type, $event->getArgument('data')->default);

        if (!empty($data->unique)) {
            $this->isUniqueFieldWithoutDuplicates($params['scope'], $data->name);
        }

        if (!empty($pattern = $data->pattern)) {
            if (!preg_match("/^\/(.*)\/$/", $pattern)) {
                throw new BadRequest($this->getLanguage()->translate('regexNotValid', 'exceptions', 'FieldManager'));
            }

            $field = Util::toUnderScore($data->name);

            if (!$this->getMetadata()->get(['entityDefs', $params['scope'], 'fields', $field])) {
                return;
            }

            $table = Util::toUnderScore($params['scope']);

            $fields = [$field];
            if ($this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    $languageField = $field . '_' . strtolower($locale);
                    if ($this->getMetadata()->get(['entityDefs', $params['scope'], 'fields', Util::toCamelCase($languageField)])) {
                        $fields[] = $languageField;
                    }
                }
            }

            $wheres = [];
            foreach ($fields as $v) {
                $wheres[] = "$v IS NOT NULL AND $v != ''";
            }

            $records = $this
                ->getEntityManager()
                ->getPDO()
                ->query("SELECT * FROM $table WHERE deleted=0 AND (" . implode(' OR ', $wheres) . ")")
                ->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($records as $valueData) {
                foreach ($fields as $v) {
                    if (!preg_match($pattern, $valueData[$v])) {
                        throw new BadRequest($this->getLanguage()->translate('someFieldDontMathToPattern', 'exceptions', 'FieldManager'));
                    }
                }
            }
        }
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
     * @param string $scope
     * @param string $field
     *
     * @throws BadRequest
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function isUniqueFieldWithoutDuplicates(string $scope, string $field): void
    {
        $defs = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $field], []);

        if (isset($defs['type'])) {
            $table = Util::toUnderScore($scope);
            $field = Util::toUnderScore($field);

            switch ($defs['type']) {
                case 'asset':
                    $this->removeDeletedDuplicate($table, [$field . '_id']);

                    $sql = "SELECT COUNT(*) FROM $table WHERE $table.{$field}_id IS NOT NULL AND deleted = 0 GROUP BY $table.{$field}_id HAVING COUNT($table.{$field}_id) > 1";
                    $result = $this->fetch($sql);
                    break;
                case 'currency':
                    $this->removeDeletedDuplicate($table, [$field, $field . '_currency']);

                    $sql = "SELECT COUNT(*) FROM $table WHERE $table.$field IS NOT NULL AND {$field}_currency IS NOT NULL AND deleted = 0 GROUP BY $table.$field, {$field}_currency HAVING COUNT($table.$field) > 1 AND COUNT({$field}_currency) > 1";
                    $result = $this->fetch($sql);
                    break;
                case 'unit':
                    $this->removeDeletedDuplicate($table, [$field, $field . '_unit']);

                    $sql = "SELECT COUNT(*) FROM $table WHERE $table.$field IS NOT NULL AND {$field}_unit IS NOT NULL AND deleted = 0 GROUP BY $table.$field, {$field}_unit HAVING COUNT($table.$field) > 1 AND COUNT({$field}_unit) > 1";
                    $result = $this->fetch($sql);
                    break;
                default:
                    $this->removeDeletedDuplicate($table, [$field]);

                    $sql = "SELECT COUNT(*) FROM $table WHERE $table.$field IS NOT NULL AND deleted = 0 GROUP BY $table.$field HAVING COUNT($table.$field) > 1;";
                    $result = $this->fetch($sql);

                    if (!$result && !empty($defs['isMultilang']) && $this->getConfig()->get('isMultilangActive', false)) {
                        foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                            $locale = strtolower($locale);

                            $this->removeDeletedDuplicate($table, [$field . '_' . $locale]);

                            $sql = "SELECT COUNT(*) FROM $table WHERE $table.{$field}_$locale IS NOT NULL AND deleted = 0 GROUP BY $table.{$field}_$locale HAVING COUNT($table.{$field}_$locale) > 1;";
                            $result = $result || $this->fetch($sql);
                        }
                    }
            }

            if (!empty($result)) {
                $message = $this
                    ->getLanguage()
                    ->translate('someFieldNotUnique', 'exceptions', 'FieldManager');

                throw new BadRequest($message);
            }
        }
    }

    /**
     * @param string $table
     * @param array $fields
     */
    protected function removeDeletedDuplicate(string $table, array $fields): void
    {
        $sql = "SELECT DISTINCT first.id AS id FROM $table as first, $table as second WHERE first.id <> second.id AND first.deleted = 1";
        foreach ($fields as $field) {
            $sql .= " AND first.$field = second.$field";
        }

        $notUniqueDeletedIds = $this
            ->getEntityManager()
            ->nativeQuery($sql)
            ->fetchAll(\PDO::FETCH_ASSOC|\PDO::FETCH_COLUMN);

        if (!empty($notUniqueDeletedIds)) {
            $ids = "'" . implode("','", $notUniqueDeletedIds) . "'";

            $this
                ->getEntityManager()
                ->nativeQuery("DELETE FROM $table WHERE id IN ($ids)");
        }
    }

    /**
     * @param string $sql
     *
     * @return mixed
     */
    protected function fetch(string $sql)
    {
        $pdo = $this
            ->getContainer()
            ->get('pdo');
        $sth = $pdo->prepare($sql);
        $sth->execute();

        return $sth->fetch();
    }
}
