<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Core\Exceptions\InternalServerError;
use Espo\ORM\Entity;
use Treo\Core\EventManager\Event;

/**
 * Class AssetEntity
 */
class AttachmentEntity extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws InternalServerError
     */
    public function beforeSave(Event $event)
    {
        $entity = $event->getArgument('entity');
        if (!$entity->isNew()) {
            if ($entity->get('sourceId')) {
                $this->copyFile($entity);
            } elseif (($entity->isAttributeChanged("relatedId") || $entity->isAttributeChanged("relatedType")) && !in_array($entity->get("relatedType"), $this->skipTypes())) {
                $this->getService($entity->getEntityType())->moveFromTmp($entity);
            }
        }
    }

    /**
     * @return array
     */
    protected function skipTypes()
    {
        return $this->getMetadata()->get(['attachment', 'skipEntities']) ?? [];
    }

    /**
     * @param Entity $entity
     *
     * @throws InternalServerError
     */
    protected function copyFile(Entity $entity): void
    {
        $repository = $this->getEntityManager()->getRepository($entity->getEntityType());
        $path = $repository->copy($entity);

        if (!$path) {
            throw new InternalServerError($this->getLanguage()->translate("Can't copy file", 'exceptions', 'Global'));
        }

        $entity->set(
            [
                'sourceId'        => null,
                'storageFilePath' => $path,
            ]
        );
    }
}
