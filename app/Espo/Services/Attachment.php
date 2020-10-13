<?php

namespace Espo\Services;

use \Espo\ORM\Entity;

use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\Error;

class Attachment extends Record
{
    protected $notFilteringAttributeList = ['contents'];

    protected $attachmentFieldTypeList = ['file', 'image', 'attachmentMultiple'];

    protected $inlineAttachmentFieldTypeList = ['text', 'wysiwyg'];

    public function upload($fileData)
    {
        if (!$this->getAcl()->checkScope('Attachment', 'create')) {
            throw new Forbidden();
        }

        $arr = explode(',', $fileData);
        if (count($arr) > 1) {
            list($prefix, $contents) = $arr;
            $contents = base64_decode($contents);
        } else {
            $contents = '';
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment');
        $attachment->set('contents', $contents);
        $this->getEntityManager()->saveEntity($attachment);

        return $attachment;
    }

    public function createEntity($attachment)
    {
        if (!empty($attachment->file)) {
            $arr = explode(',', $attachment->file);
            $contents = '';
            if (count($arr) > 1) {
                $contents = $arr[1];
            }

            $contents = base64_decode($contents);
            $attachment->contents = $contents;

            $relatedEntityType = null;
            $field = null;
            $role = 'Attachment';
            if (isset($attachment->parentType)) {
                $relatedEntityType = $attachment->parentType;
            } else if (isset($attachment->relatedType)) {
                $relatedEntityType = $attachment->relatedType;
            }
            if (isset($attachment->field)) {
                $field = $attachment->field;
            }
            if (isset($attachment->role)) {
                $role = $attachment->role;
            }
            if (!$relatedEntityType || !$field) {
                throw new BadRequest("Params 'field' and 'parentType' not passed along with 'file'.");
            }

            $fieldType = $this->getMetadata()->get(['entityDefs', $relatedEntityType, 'fields', $field, 'type']);
            if (!$fieldType) {
                throw new Error("Field '{$field}' does not exist.");
            }

            if (
                !$this->getAcl()->checkScope($relatedEntityType, 'create')
                &&
                !$this->getAcl()->checkScope($relatedEntityType, 'edit')
            ) {
                throw new Forbidden("No access to " . $relatedEntityType . ".");
            }

            if (in_array($field, $this->getAcl()->getScopeForbiddenFieldList($relatedEntityType, 'edit'))) {
                throw new Forbidden("No access to field '" . $field . "'.");
            }

            $size = mb_strlen($contents, '8bit');

            if ($role === 'Attachment') {
                if (!in_array($fieldType, $this->attachmentFieldTypeList)) {
                    throw new Error("Field type '{$fieldType}' is not allowed for attachment.");
                }
                $maxSize = $this->getMetadata()->get(['entityDefs', $relatedEntityType, 'fields', $field, 'maxFileSize']);
                if (!$maxSize) {
                    $maxSize = $this->getConfig()->get('attachmentUploadMaxSize');
                }
                if ($maxSize) {
                    if ($size > $maxSize * 1024 * 1024) {
                        throw new Error("File size should not exceed {$maxSize}Mb.");
                    }
                }

            } else if ($role === 'Inline Attachment') {
                if (!in_array($fieldType, $this->inlineAttachmentFieldTypeList)) {
                    throw new Error("Field '{$field}' is not allowed to have inline attachment.");
                }
                $inlineAttachmentUploadMaxSize = $this->getConfig()->get('inlineAttachmentUploadMaxSize');
                if ($inlineAttachmentUploadMaxSize) {
                    if ($size > $inlineAttachmentUploadMaxSize * 1024 * 1024) {
                        throw new Error("File size should not exceed {$inlineAttachmentUploadMaxSize}Mb.");
                    }
                }
            } else {
                throw new BadRequest("Not supported attachment role.");
            }
        }

        $entity = parent::createEntity(clone $attachment);

        if (!empty($attachment->file)) {
            $entity->clear('contents');
        }

        return $entity;
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $storage = $entity->get('storage');
        if ($storage && !$this->getMetadata()->get(['app', 'fileStorage', 'implementationClassNameMap', $storage])) {
            $entity->clear('storage');
        }
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        $storage = $entity->get('storage');
        if ($storage && !$this->getMetadata()->get(['app', 'fileStorage', 'implementationClassNameMap', $storage])) {
            $entity->clear('storage');
        }
    }
}

