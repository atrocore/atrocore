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

use Atro\Core\DataManager;
use Atro\Core\Exceptions\Error;
use Atro\Core\Templates\Services\ReferenceData;
use Espo\ORM\Entity as OrmEntity;

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

    protected function duplicateFields($entity, $duplicatingEntity): void
    {
        $fieldsDefs = $linksDefs = [];

        $needToUpdate = false;

        foreach ($this->getMetadata()->get(['entityDefs', $duplicatingEntity->get('code'), 'fields'], []) as $field => $defs) {
            if ($field == 'id' || empty($defs['isCustom'])) {
                continue;
            }

            $fieldsDefs[$field] = $defs;

            if (in_array($defs['type'], ['link', 'linkMultiple'])) {
                $linkDefs = $this->getMetadata()->get(['entityDefs', $duplicatingEntity->get('code'), 'links', $field], []);

                if (!empty($linkDefs['foreign']) && !empty($linkDefs['entity'])) {
                    $foreign = $linkDefs['foreign'];
                    $duplicateForeign = lcfirst($entity->get('code')) . 's';

                    $linkDefs['foreign'] = $duplicateForeign;

                    $foreignDefs = $this->getMetadata()->get(['entityDefs', $linkDefs['entity']], []);

                    if (!empty($foreignDefs['fields'][$foreign])) {
                        $this->getMetadata()->set('entityDefs', $linkDefs['entity'], [
                            'fields' => [
                                $duplicateForeign => $foreignDefs['fields'][$foreign]
                            ]
                        ]);
                    }

                    if (!empty($foreignDefs['links'][$foreign])) {
                        $foreignLinkDefs = $foreignDefs['links'][$foreign];

                        if (!empty($foreignLinkDefs['entity'])) {
                            $foreignLinkDefs['entity'] = $entity->get('code');
                        }

                        if (!empty($foreignLinkDefs['relationName'])) {
                            if (str_starts_with($foreignLinkDefs['relationName'], lcfirst($duplicatingEntity->get('code')))) {
                                $foreignLinkDefs['relationName'] = str_replace(lcfirst($duplicatingEntity->get('code')), lcfirst($entity->get('code')), $foreignLinkDefs['relationName']);
                            } elseif (str_ends_with($foreignLinkDefs['relationName'], ucfirst($duplicatingEntity->get('code')))) {
                                $foreignLinkDefs['relationName'] = str_replace(ucfirst($duplicatingEntity->get('code')), ucfirst($entity->get('code')), $foreignLinkDefs['relationName']);
                            }

                            $linkDefs['relationName'] = $foreignLinkDefs['relationName'];
                        }

                        $this->getMetadata()->set('entityDefs', $linkDefs['entity'], [
                            'links' => [
                                $duplicateForeign => $foreignLinkDefs
                            ]
                        ]);
                    }

                    $linksDefs[$field] = $linkDefs;
                }
            }

            $needToUpdate = true;
        }

        if ($needToUpdate) {
            $this->getMetadata()->set('entityDefs', $entity->get('code'), ['fields' => $fieldsDefs]);
            if (!empty($linkDefs)) {
                $this->getMetadata()->set('entityDefs', $entity->get('code'), ['links' => $linksDefs]);
            }
            $this->getMetadata()->save();

            $this->getDataManager()->rebuild();
        }
    }

    protected function getFieldsThatConflict(OrmEntity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }
}
