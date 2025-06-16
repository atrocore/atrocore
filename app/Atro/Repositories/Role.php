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

use Atro\Core\DataManager;
use Atro\Entities\Role as RoleEntity;
use Espo\Core\AclManager;
use Espo\ORM\Entity;

class Role extends \Espo\Core\ORM\Repositories\RDB
{
    public const ACTIONS = [
        'create',
        'read',
        'edit',
        'delete',
        'stream',
        'createAttributeValue',
        'deleteAttributeValue'
    ];

    public static function createCacheKey(RoleEntity $role): string
    {
        return "role_acl_{$role->get('id')}";
    }

    public function clearAclCache(): bool
    {
        return $this->getDataManager()->clearCache();
    }

    public function getAclData(RoleEntity $role): \stdClass
    {
        $key = self::createCacheKey($role);

        $res = $this->getDataManager()->getCacheData($key);
        if ($res === null) {
            $res = [
                'scopes' => [],
                'fields' => []
            ];

            foreach ($role->get('scopes') ?? [] as $roleScope) {
                $scopeName = $roleScope->get('name');
                $res['scopes'][$scopeName] = null;
                if ($roleScope->get('hasAccess')) {
                    foreach (self::ACTIONS as $action) {
                        if (!empty($roleScope->get("{$action}Action"))) {
                            $res['scopes'][$scopeName][$action] = $roleScope->get("{$action}Action");
                        }
                    }
                    foreach ($roleScope->get('fields') ?? [] as $field) {
                        $fieldName = $field->get('name');
                        $res['fields'][$scopeName][$fieldName]['read'] = !empty($field->get("readAction")) ? 'yes' : 'no';
                        $res['fields'][$scopeName][$fieldName]['edit'] = !empty($field->get("editAction")) ? 'yes' : 'no';
                    }

                    if (class_exists('\\Pim\\Services\\Attribute')) {
                        /** @var \Pim\Services\Attribute $attributeService */
                        $attributeService = $this->getInjection('container')->get('serviceFactory')->create('Attribute');

                        $roleAttributes = $roleScope->get('attributes');
                        if (!empty($roleAttributes[0])) {
                            $attributesIds = array_column($roleAttributes->toArray(), 'attributeId');
                            $attributesDefs = $attributeService->getAttributesDefs($scopeName, $attributesIds);

                            foreach ($roleAttributes as $roleAttribute) {
                                foreach ($attributesDefs as $fieldName => $defs) {
                                    if (empty($defs['attributeId']) || $defs['attributeId'] !== $roleAttribute->get('attributeId')) {
                                        continue;
                                    }
                                    $res['fields'][$scopeName][$fieldName]['read'] = !empty($roleAttribute->get("readAction")) ? 'yes' : 'no';
                                    $res['fields'][$scopeName][$fieldName]['edit'] = !empty($roleAttribute->get("editAction")) ? 'yes' : 'no';
                                }
                            }
                        }

                        foreach ($roleScope->get('attributePanels') ?? [] as $roleAttributePanel) {
                            echo '<pre>';
                            print_r($roleAttributePanel->get('attributePanelId'));
                            die();

                        }
                    }
                }
            }

            $this->getDataManager()->setCacheData($key, $res);
        }

        return json_decode(json_encode($res));
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this
            ->getAclManager()
            ->clearAclCache();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('container')->get('dataManager');
    }
}
