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
    public function beforeSave(Entity $entity, array $options = [])
    {
        $actions = ['create', 'read', 'edit', 'delete', 'stream'];

        if (empty($entity->get('hasAccess'))) {
            foreach ($actions as $action) {
                $entity->set("{$action}Action", null);
            }
        } else {
            $scope = $entity->get('name');
            $aclActionLevelListMap = $this->getMetadata()->get("scopes.$scope.aclActionLevelListMap");

            if (is_array($aclActionLevelListMap)) {
                foreach ($actions as $action) {
                    if (!in_array($scope, $aclActionLevelListMap)) {
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
}
