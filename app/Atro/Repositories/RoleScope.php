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

namespace Atro\Repositories;

use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class RoleScope extends Base
{
    protected array $actions = ['create', 'read', 'edit', 'delete', 'stream'];

    public function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($entity->get('hasAccess'))) {
            foreach ($this->actions as $action) {
                $entity->set("{$action}Action", null);
            }
        } else {
            $scope = $entity->get('name');
            $aclActionList = $this->getMetadata()->get("scopes.$scope.aclActionList");

            if (is_array($aclActionList)) {
                foreach ($this->actions as $action) {
                    if (!in_array($scope, $aclActionList)) {
                        $entity->set("{$action}Action", null);
                    }
                }
            }

            if ($this->getMetadata()->get("scopes.$scope.streamDisabled")) {
                $entity->set("streamAction", null);
            }
        }

        if ($entity->isNew()) {
            $exists = $this
                ->where([
                    'roleId' => $entity->get('roleId'),
                    'name'   => $entity->get('name')
                ])
                ->findOne();

            if (!empty($exists)) {
                $fieldName = $this->getLanguage()->translate('name', 'fields', 'RoleScope');
                $message = $this->getLanguage()->translate('notUniqueRecordField', 'exceptions');
                throw new NotUnique(sprintf($message, $fieldName));
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $role = $this->getEntityManager()->getRepository('Role')->get($entity->get('roleId'));
        if (!empty($role)) {
            $data = empty($role->get('data')) ? [] : json_decode(json_encode($role->get('data')), true);
            $data[$entity->get('name')] = [];
            if (!empty($entity->get('hasAccess'))) {
                foreach ($this->actions as $action) {
                    if (!empty($entity->get("{$action}Action"))) {
                        $data[$entity->get('name')][$action] = $entity->get("{$action}Action");
                    }
                }
            }
            $role->set('data', $data);
            $this->getEntityManager()->saveEntity($role);
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $role = $this->getEntityManager()->getRepository('Role')->get($entity->get('roleId'));
        if (!empty($role)) {
            $data = empty($role->get('data')) ? [] : json_decode(json_encode($role->get('data')), true);
            if (isset($data[$entity->get('name')])) {
                unset($data[$entity->get('name')]);
            }
            $role->set('data', $data);
            $this->getEntityManager()->saveEntity($role);
        }
    }
}
