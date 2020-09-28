<?php

declare(strict_types=1);

namespace Treo\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;

/**
 * Service Attachment
 *
 * @author r.ratsun r.ratsun@zinitsolutions.com
 */
class Attachment extends \Espo\Services\Attachment
{
    /**
     * @var array
     */
    protected $inlineAttachmentFieldTypeList = ['text', 'wysiwyg', 'wysiwygMultiLang'];

    /**
     * @param Entity $entity
     * @return mixed
     * @throws NotFound
     */
    public function moveFromTmp(Entity $entity)
    {
        if ($entity->get("storageFilePath")) {
            return true;
        }

        if (!file_exists($entity->get('tmpPath'))) {
            throw new NotFound("File not found");
        }

        return $this->getRepository()->moveFromTmp($entity);
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws NotFound
     */
    public function moveMultipleAttachment(Entity $entity)
    {
        if ($this->moveFromTmp($entity)) {
            return $this->getEntityManager()->saveEntity($entity, ['skipAll' => true]);
        }

        return false;
    }
}
