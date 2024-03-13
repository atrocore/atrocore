<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Espo\Services;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\NotFound;

class DataPrivacy extends \Espo\Core\Services\Base
{
    protected function init()
    {
        $this->addDependency('fileManager');
        $this->addDependency('acl');
        $this->addDependency('aclManager');
        $this->addDependency('metadata');
        $this->addDependency('serviceFactory');
        $this->addDependency('dateTime');
        $this->addDependency('number');
        $this->addDependency('entityManager');
        $this->addDependency('defaultLanguage');
        $this->addDependency('fieldManagerUtil');
        $this->addDependency('user');
    }

    protected function getAcl()
    {
        return $this->getInjection('acl');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getFileManager()
    {
        return $this->getInjection('fileManager');
    }

    protected function getEntityManager()
    {
        return $this->getInjection('entityManager');
    }

    public function erase($entityType, $id, array $fieldList)
    {
        if ($this->getAcl()->get('dataPrivacyPermission') === 'no') {
            throw new Forbidden();
        }

        if ($this->getServiceFactory()->checkExists($entityType)) {
            $service = $this->getServiceFactory()->create($entityType);
        } else {
            $service = $this->getServiceFactory()->create('Record');
            $service->setEntityType($entityType);
        }

        $entity = $this->getEntityManager()->getEntity($entityType, $id);

        if (!$entity) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden("No edit access.");
        }

        $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($entityType, 'edit');

        foreach ($fieldList as $field) {
            if (in_array($field, $forbiddenFieldList)) {
                throw new Forbidden("Field '{$field}' is forbidden to edit.");
            }
        }

        $service->loadAdditionalFields($entity);

        $filedManager = $this->getInjection('fieldManagerUtil');

        foreach ($fieldList as $field) {
            $type = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $field, 'type']);
            $attributeList = $filedManager->getActualAttributeList($entityType, $field);

            if ($type === 'file' || $type === 'image') {
                $attachmentId = $entity->get($field . 'Id');
                if ($attachmentId) {
                    $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
                    $this->getEntityManager()->removeEntity($attachment);
                }

            }
            else if ($type === 'attachmentMultiple') {
                $attachmentList = $entity->get($field);
                foreach ($attachmentList as $attachment) {
                    $this->getEntityManager()->removeEntity($attachment);
                }
            }

            foreach ($attributeList as $attribute) {
                if (in_array($entity->getAttributeType($attribute), [$entity::VARCHAR, $entity::TEXT]) && $entity->get($attribute)) {
                    $entity->set($attribute, null);
                } else {
                    $entity->set($attribute, null);
                }
            }
        }

        $this->getEntityManager()->saveEntity($entity);

        return true;
    }
}

