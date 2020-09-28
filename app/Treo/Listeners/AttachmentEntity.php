<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Core\Exceptions\Error;
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
        if ($this->isDuplicate($entity)) {
            $this->copyFile($entity);
        } elseif (!$entity->isNew()
            && $this->isChangeRelation($entity)
            && !in_array($entity->get("relatedType"), $this->skipTypes())
        ) {
            $this->moveFromTmp($entity);
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
     * @return bool
     */
    protected function isChangeRelation(Entity $entity): bool
    {
        return $entity->isAttributeChanged("relatedId") || $entity->isAttributeChanged("relatedType");
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws Error
     */
    protected function moveFromTmp(Entity $entity)
    {
        if ($entity->isNew()) {
            return true;
        }

        if (!$this->getService($entity->getEntityType())->moveFromTmp($entity)) {
            throw new Error();
        }
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isDuplicate(Entity $entity): bool
    {
        return (!$entity->isNew() && $entity->get('sourceId'));
    }

    /**
     * @param Entity $entity
     *
     * @throws InternalServerError
     */
    protected function copyFile(Entity $entity): void
    {
        $repository = $this->getEntityManager()->getRepository($entity->getEntityType());
        $path       = $repository->copy($entity);

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
