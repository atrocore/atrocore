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
use Atro\Repositories\Role as RoleRepository;
use Espo\ORM\Entity;

class RoleScope extends Base
{
    protected $mandatorySelectAttributeList = ['roleId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if ($entity->get('hasAccess')) {
            $role = $this->getRoleRepository()->get($entity->get('roleId'));
            if (!empty($role)) {
                $aclData = $this->getRoleRepository()->getAclData($role);
                $entity->set('accessData', [
                    'scopeData'  => $aclData->scopes->{$entity->get('name')} ?? null,
                    'fieldsData' => $aclData->fields->{$entity->get('name')} ?? null
                ]);
            }
        }
    }

    protected function getRoleRepository(): RoleRepository
    {
        return $this->getEntityManager()->getRepository('Role');
    }
}
