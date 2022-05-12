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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Espo\Listeners;

use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;

/**
 * Class FieldManager
 */
class FieldManager extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function afterSave(Event $event)
    {
        $scope = $event->getArgument('scope');
        $field = $event->getArgument('field');

        // get old field defs
        $oldDefs = $event->getArgument('oldFieldDefs');

        if (in_array($oldDefs['type'], ['enum', 'multiEnum'])) {
            // get current field defs
            $defs = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $field]);

            if ($oldDefs['type'] === 'enum') {
                $this->updateEnumValue($scope, $field, $oldDefs, $defs);
            }

            if ($oldDefs['type'] === 'multiEnum') {
                $this->updateMultiEnumValue($scope, $field, $oldDefs, $defs);
            }
        }
    }

    protected function updateEnumValue(string $scope, string $field, array $oldDefs, array $defs): void
    {
        if (!$this->isEnumTypeValueValid($defs)) {
            return;
        }

        if (!isset($oldDefs['optionsIds'])) {
            return;
        }

        $tableName = Util::toUnderScore($scope);
        $columnName = Util::toUnderScore($field);

        // prepare became values
        $becameValues = [];
        foreach ($defs['optionsIds'] as $k => $v) {
            foreach ($oldDefs['optionsIds'] as $k1 => $v1) {
                if ($v1 === $v) {
                    $becameValues[$oldDefs['options'][$k1]] = $defs['options'][$k];
                }
            }
        }

        /** @var array $records */
        $records = $this
            ->getEntityManager()
            ->getRepository($scope)
            ->select(['id', $field])
            ->find()
            ->toArray();

        foreach ($records as $record) {
            $value = "null";
            if (!empty($becameValues[$record[$field]])) {
                $value = "'{$becameValues[$record[$field]]}'";
            }
            $this->getEntityManager()->getPDO()->exec("UPDATE {$tableName} SET `{$columnName}`={$value} WHERE id='{$record['id']}'");
        }
    }

    protected function updateMultiEnumValue(string $scope, string $field, array $oldDefs, array $defs): void
    {
        if (!$this->isEnumTypeValueValid($defs)) {
            return;
        }

        if (!isset($oldDefs['optionsIds'])) {
            return;
        }

        $tableName = Util::toUnderScore($scope);
        $columnName = Util::toUnderScore($field);

        // prepare became values
        $becameValues = [];
        foreach ($defs['optionsIds'] as $k => $v) {
            foreach ($oldDefs['optionsIds'] as $k1 => $v1) {
                if ($v1 === $v) {
                    $becameValues[$oldDefs['options'][$k1]] = $defs['options'][$k];
                }
            }
        }

        /** @var array $records */
        $records = $this
            ->getEntityManager()
            ->getRepository($scope)
            ->select(['id', $field])
            ->find()
            ->toArray();

        foreach ($records as $record) {
            if (!empty($record[$field])) {
                $newValues = [];
                foreach ($record[$field] as $value) {
                    if (isset($becameValues[$value])) {
                        $newValues[] = $becameValues[$value];
                    }
                }
                $record[$field] = $newValues;
            }

            $this
                ->getEntityManager()
                ->getPDO()
                ->exec("UPDATE {$tableName} SET {$columnName}='" . Json::encode($record[$field]) . "' WHERE id='{$record['id']}'");
        }
    }

    /**
     * @param array $defs
     *
     * @return bool
     * @throws BadRequest
     */
    protected function isEnumTypeValueValid(array $defs): bool
    {
        if (!empty($defs['options'])) {
            foreach (array_count_values($defs['options']) as $count) {
                if ($count > 1) {
                    throw new BadRequest($this->exception('fieldValueShouldBeUnique'));
                }
            }
        }

        return true;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getLanguage()->translate($key, 'exceptions', 'Global');
    }
}
