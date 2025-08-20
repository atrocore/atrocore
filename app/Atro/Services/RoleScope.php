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
    protected $mandatorySelectAttributeList = ['roleId', 'createAction', 'readAction', 'editAction', 'deleteAction', 'streamAction', 'createAttributeValueAction', 'deleteAttributeValueAction'];

    public function getSelectAttributeList($params)
    {
        $res = parent::getSelectAttributeList($params);

        foreach (RoleRepository::ACTIONS as $action) {
            if (!in_array("{$action}Action", $res) && !$this->getMetadata()->get("entityDefs.RoleScope.fields.{$action}Action")) {
                $res[] = "{$action}Action";
            }
        }

        return $res;
    }

    public function getRoleAccessData(Entity $entity): array
    {
        $accessData = [];
        foreach (RoleRepository::ACTIONS as $action) {
            if ($entity->get("{$action}Action") !== null) {
                $accessData['scopeData'][$action] = $entity->get("{$action}Action");
            }
        }

        foreach ($entity->get('fields') ?? [] as $field) {
            $accessData['fieldsData'][$field->get('name')]['read'] = !empty($field->get("readAction")) ? 'yes' : 'no';
            $accessData['fieldsData'][$field->get('name')]['edit'] = !empty($field->get("editAction")) ? 'yes' : 'no';
        }

        foreach ($entity->get('attributePanels') ?? [] as $row) {
            $attributePanel = $this->getEntityManager()->getEntity('AttributePanel', $row->get('attributePanelId'));
            if (empty($attributePanel)) {
                continue;
            }
            $accessData['attributePanelsData'][$attributePanel->get('id')] = [
                'name'       => $attributePanel->get('name'),
                'accessData' => [
                    'read' => !empty($row->get("readAction")) ? 'yes' : 'no',
                    'edit' => !empty($row->get("editAction")) ? 'yes' : 'no'
                ]
            ];
        }

        foreach ($entity->get('attributes') ?? [] as $row) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $row->get('attributeId'));
            if (empty($attribute)) {
                continue;
            }
            $accessData['attributesData'][$attribute->get('id')] = [
                'name'       => $attribute->get('name'),
                'accessData' => [
                    'read' => !empty($row->get("readAction")) ? 'yes' : 'no',
                    'edit' => !empty($row->get("editAction")) ? 'yes' : 'no'
                ]
            ];
        }

        return $accessData;
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('nameLabel', $this->getInjection('language')->translate($entity->get('name'), 'scopeNames'));

        $entity->set('accessData', null);
        if ($entity->get('hasAccess')) {
            $entity->set('accessData', $this->getRoleAccessData($entity));
        }
    }
}
