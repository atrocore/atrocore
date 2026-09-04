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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\AclManager;
use Espo\ORM\Entity;

class RoleScopeField extends Base
{
    public function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('roleScopeId') || $entity->isAttributeChanged('name')) {
            if (!$entity->isNew()) {
                throw new BadRequest("Field and Scope can not be changed.");
            }
            $exists = $this
                ->where([
                    'roleScopeId' => $entity->get('roleScopeId'),
                    'name'        => $entity->get('name')
                ])
                ->findOne();

            if (!empty($exists)) {
                $fieldName = $this->getLanguage()->translate('name', 'fields', 'RoleScopeField');
                $message = $this->getLanguage()->translate('notUniqueRecordField', 'exceptions');
                throw new NotUnique(sprintf($message, $fieldName));
            }

            $this->validateFieldIsConfigurable($entity);
        }

        if (empty($entity->get('readAction'))) {
            $entity->set('editAction', false);
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * An one-to-one relation is stored in the field of the entity which owns it, the field on the other side is
     * only a virtual one. Access rights for such a relation are therefore defined by the owning field and
     * configuring them for the virtual field would have no effect on it.
     *
     * @param Entity $entity
     *
     * @return void
     * @throws BadRequest
     */
    protected function validateFieldIsConfigurable(Entity $entity): void
    {
        $roleScope = $this->getEntityManager()->getEntity('RoleScope', (string)$entity->get('roleScopeId'));
        if (empty($roleScope)) {
            return;
        }

        $scopeName = $roleScope->get('name');
        $linkDefs = $this->getMetadata()->get(['entityDefs', $scopeName, 'links', $entity->get('name')], []);

        if (($linkDefs['type'] ?? null) !== 'hasOne' || empty($linkDefs['entity']) || empty($linkDefs['foreign'])) {
            return;
        }

        throw new BadRequest(
            sprintf(
                $this->getLanguage()->translate('virtualOneToOneFieldNotConfigurable', 'exceptions'),
                $this->getLanguage()->translate($entity->get('name'), 'fields', $scopeName),
                $this->getLanguage()->translate($linkDefs['foreign'], 'fields', $linkDefs['entity']),
                $this->getLanguage()->translate($linkDefs['entity'], 'scopeNames')
            )
        );
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this
            ->getAclManager()
            ->clearAclCache();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

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
}
