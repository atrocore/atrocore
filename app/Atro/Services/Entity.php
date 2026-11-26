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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\DataManager;
use Atro\Core\Templates\Services\ReferenceData;
use Espo\ORM\Entity as OrmEntity;

class Entity extends ReferenceData
{
    public function resetToDefault(string $scope): bool
    {
        if (empty($this->getRepository()->get($scope))) {
            throw new NotFound();
        }

        if ($this->getMetadata()->get("scopes.$scope.isCustom")) {
            throw new BadRequest(sprintf($this->container->get('language')->translate('customEntityCannotBeReset', 'exceptions', 'Entity'), $scope));
        }

        @unlink("data/metadata/clientDefs/{$scope}.json");
        @unlink("data/metadata/scopes/{$scope}.json");

        $this->getMetadata()->delete('entityDefs', $scope, [
            'collection.sortBy',
            'collection.asc'
        ]);
        $this->getMetadata()->save();

        $this->container->get('dataManager')->clearCache();

        return true;
    }

    /**
     * An entity of a type has the id of the entity and is deleted via the "Entity" service.
     */
    public function deleteEntity(string $id): bool
    {
        if ($this->entityType !== 'Entity') {
            return $this->getServiceFactory()->create('Entity')->deleteEntity($id);
        }

        return parent::deleteEntity($id);
    }

    /**
     * The entities of the system and of the modules cannot be deleted, their configuration can be reset to default.
     */
    public function putAclMeta(OrmEntity $entity): void
    {
        parent::putAclMeta($entity);

        $isCustom = !empty($this->getMetadata()->get(['scopes', $entity->get('id'), 'isCustom']));

        $entity->setAvailableAction('resetToDefault', !$isCustom);
        if (!$isCustom) {
            $entity->setAvailableAction('delete', false);
        }

        // a derived entity is created from its primary entity
        if (!empty($entity->get('primaryEntityId'))) {
            $entity->setAvailableAction('duplicate', false);
        }
    }

    public function createEntity(\stdClass $attachment): string
    {
        if (!empty($attachment->_duplicatingEntityId)) {
            $duplicating = $this->getRepository()->get($attachment->_duplicatingEntityId);
            if (!empty($duplicating) && !empty($duplicating->get('primaryEntityId'))) {
                throw new BadRequest($this->container->get('language')->translate('derivativeCannotBeDuplicated', 'exceptions', 'Entity'));
            }
        }

        return parent::createEntity($attachment);
    }

    /**
     * The supertype is validated before the general validation of the link values, it explains the reason of the error.
     */
    protected function beforeCreateEntity(OrmEntity $entity, $data)
    {
        $supertype = $data->supertypeEntityId ?? null;
        if (!empty($supertype) && is_string($supertype)) {
            $supertypeDefs = $this->getMetadata()->get(['scopes', $supertype]);
            if (empty($supertypeDefs) || !empty($supertypeDefs['emHidden'])) {
                throw new BadRequest(sprintf($this->container->get('language')->translate('supertypeIsNotFound', 'exceptions', 'Entity'), $supertype));
            }

            if (($supertypeDefs['type'] ?? null) !== 'Base' || !empty($supertypeDefs['primaryEntityId'])) {
                throw new BadRequest($this->container->get('language')->translate('supertypeMustBeBase', 'exceptions', 'Entity'));
            }
        }

        parent::beforeCreateEntity($entity, $data);
    }

    /**
     * Some entities allow to change only some of their parameters.
     */
    protected function getReadOnlyFields(OrmEntity $entity): array
    {
        $editable = $this->getMetadata()->get(['scopes', $entity->get('id'), 'onlyEditableEmFields']);
        if (empty($editable)) {
            return [];
        }

        $fields = [];
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $defs) {
            if (!empty($defs['type']) && !in_array($field, $editable, true)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function getSelectAttributeList($params)
    {
        return [];
    }

    protected function duplicateFields($entity, $duplicatingEntity): void
    {
        $fieldsDefs = $linksDefs = [];

        $needToUpdate = false;

        foreach ($this->getMetadata()->get(['entityDefs', $duplicatingEntity->get('code'), 'fields'], []) as $field => $defs) {
            if ($field == 'id') {
                continue;
            }

            if (empty($defs['isCustom'])) {
                continue;
            }

            if ($defs['type'] == 'linkMultiple') {
                continue;
            }

            $fieldsDefs[$field] = $defs;

            if ($defs['type'] == 'link') {
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

    protected function getDataManager(): DataManager
    {
        return $this->container->get('dataManager');
    }
}
