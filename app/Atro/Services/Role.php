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
use Espo\Core\ServiceFactory;
use Espo\ORM\Entity;

class Role extends Base
{
    protected function duplicateScopes(Entity $entity, Entity $duplicatingEntity): void
    {
        $scopes = $duplicatingEntity->get('scopes');

        if (count($scopes) === 0) {
            return;
        }

        $roleScopeService = $this->getServiceFactory()->create('RoleScope');

        foreach ($scopes as $scope) {
            $data = $roleScopeService->getDuplicateAttributes($scope->get('id'));
            $data->roleId = $entity->get('id');

            try {
                $duplicateScopeId = $roleScopeService->createEntity($data);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Duplicating role scope failed: {$e->getMessage()}");
                continue;
            }

            $this->duplicateScopeFields($scope, $duplicateScopeId);
            $this->duplicateScopeAttributes($scope, $duplicateScopeId);
            $this->duplicateScopeAttributePanels($scope, $duplicateScopeId);
        }
    }

    protected function duplicateScopeFields(Entity $scope, string $duplicateScopeId): void
    {
        $service = $this->getServiceFactory()->create('RoleScopeField');

        foreach ($scope->get('fields') as $field) {
            $data = $service->getDuplicateAttributes($field->get('id'));
            $data->roleScopeId = $duplicateScopeId;

            try {
                $service->createEntity($data);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Duplicating role scope field failed: {$e->getMessage()}");
            }
        }
    }

    protected function duplicateScopeAttributes(Entity $scope, string $duplicateScopeId): void
    {
        $service = $this->getServiceFactory()->create('RoleScopeAttribute');

        foreach ($scope->get('attributes') as $attribute) {
            $data = $service->getDuplicateAttributes($attribute->get('id'));
            $data->roleScopeId = $duplicateScopeId;

            try {
                $service->createEntity($data);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Duplicating role scope attribute failed: {$e->getMessage()}");
            }
        }
    }

    protected function duplicateScopeAttributePanels(Entity $scope, string $duplicateScopeId): void
    {
        $service = $this->getServiceFactory()->create('RoleScopeAttributePanel');

        foreach ($scope->get('attributePanels') as $attributePanel) {
            $data = $service->getDuplicateAttributes($attributePanel->get('id'));
            $data->roleScopeId = $duplicateScopeId;

            try {
                $service->createEntity($data);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Duplicating role scope attribute panel failed: {$e->getMessage()}");
            }
        }
    }

    protected function init(): void
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getInjection('serviceFactory');
    }
}
