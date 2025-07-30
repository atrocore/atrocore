<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Services;

use Atro\Core\Exceptions\Error;
use Atro\Core\Templates\Services\ReferenceData;

class Entity extends ReferenceData
{
    public function resetToDefault(string $scope): bool
    {
        if ($this->getMetadata()->get("scopes.$scope.isCustom")) {
            throw new Error("Can't reset to defaults custom entity '$scope'.");
        }

        @unlink("data/metadata/clientDefs/{$scope}.json");
        @unlink("data/metadata/scopes/{$scope}.json");

        $this->getMetadata()->delete('entityDefs', $scope, [
            'collection.sortBy',
            'collection.asc'
        ]);
        $this->getMetadata()->save();

        $this->getInjection('dataManager')->clearCache();

        return true;
    }

    public function getSelectAttributeList($params)
    {
        return [];
    }

    protected function filterInput($data, string $id = null)
    {
        parent::filterInput($data, $id);

        if (!is_object($data)) {
            return;
        }

        $fields = $this->getMetadata()->get(['scopes', $id, 'onlyEditableEmFields']);
        if (!empty($fields)) {
            foreach ($data as $field => $value) {
                $fieldDefs = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $field]);
                if (empty($fieldDefs['type'])) {
                    continue;
                }

                if (!in_array($field, $fields)) {
                    unset($data->$field);
                }
            }
        }

    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }
}
