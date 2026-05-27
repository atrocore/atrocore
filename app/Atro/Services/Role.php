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

use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class Role extends Base
{
    protected function duplicateScopes(Entity $entity, Entity $duplicatingEntity): void
    {
        $scopes = $duplicatingEntity->get('scopes');

        if (count($scopes) === 0) {
            return;
        }

        $roleScopeService = $this->getInjection('serviceFactory')->create('RoleScope');
        $roleScopeFieldService = $this->getInjection('serviceFactory')->create('RoleScopeField');

        foreach ($scopes as $scope) {
            $data = $roleScopeService->getDuplicateAttributes($scope->get('id'));
            $data->roleId = $entity->get('id');

            try {
                $duplicateScopeId = $roleScopeService->createEntity($data);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Duplicating role scope failed: {$e->getMessage()}");
                continue;
            }

            foreach ($scope->get('fields') as $field) {
                $fieldData = $roleScopeFieldService->getDuplicateAttributes($field->get('id'));
                $fieldData->roleScopeId = $duplicateScopeId;

                try {
                    $roleScopeFieldService->createEntity($fieldData);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error("Duplicating role scope field failed: {$e->getMessage()}");
                }
            }
        }
    }

    protected function init(): void
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }
}
