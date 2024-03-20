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

use Atro\Core\Templates\Repositories\Hierarchy;
use Atro\ORM\DB\RDB\Mapper;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class Asset extends Hierarchy
{
    public function getPossibleTypes(Entity $attachment): array
    {
        $types = [];

        return $types;
    }

    /**
     * @inheritDoc
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        $file = $this->getEntityManager()->getEntity('Attachment', $entity->get('fileId'));
        if (empty($file)) {
            throw new BadRequest($this->getInjection('language')->translate('noAttachmentExist', 'exceptions', 'Asset'));
        }

        if ($entity->isAttributeChanged('fileId') && !$entity->isAttributeChanged('name')) {
            $entity->set('name', $file->get('name'));
        }

        // prepare name
        if (empty($entity->get('name'))) {
            $entity->set('name', $file->get('name'));
        } elseif ($entity->isAttributeChanged('name')) {
            $assetParts = explode('.', (string)$entity->get('name'));
            if (count($assetParts) > 1) {
                $assetExt = array_pop($assetParts);
            }

            $attachmentParts = explode('.', (string)$file->get('name'));
            $attachmentExt = array_pop($attachmentParts);

            if (!empty($assetExt) && $assetExt !== $attachmentExt) {
                throw new BadRequest($this->getInjection('language')->translate('fileExtensionCannotBeChanged', 'exceptions', 'Asset'));
            }

            if (!empty($fileNameRegexPattern = $this->getConfig()->get('fileNameRegexPattern')) && !preg_match($fileNameRegexPattern, implode('.', $assetParts))) {
                $msg = sprintf($this->getInjection('language')->translate('fileNameNotValidByUserRegex', 'exceptions', 'Asset'), $fileNameRegexPattern);

                throw new BadRequest($msg);
            }

            $entity->set('name', implode('.', $assetParts) . '.' . $attachmentExt);
        }

        // update file info
        if ($entity->isAttributeChanged('fileId')) {
            $this->getInjection('serviceFactory')->create('Asset')->getFileInfo($entity);
        }

        // rename file
        if (!$entity->isNew() && $entity->isAttributeChanged("name") && !$entity->isAttributeChanged('fileId')) {
            $this->getInjection('serviceFactory')->create('Attachment')->changeName($file, $entity->get('name'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('private')) {
            $file = $entity->get('file');
            if (!empty($file)) {
                $file->set('private', $entity->get('private'));
                $this->getEntityManager()->saveEntity($file);
            }
        }

        parent::afterSave($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        if (!empty($attachmentId = $entity->get('fileId'))) {
            $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
            if (!empty($attachment)) {
                $this->getEntityManager()->removeEntity($attachment);
            }
        }

        parent::afterRemove($entity, $options);
    }

    public function afterRestore($entity){
        parent::afterRestore($entity);
        if (!empty($attachmentId = $entity->get('fileId'))) {
            $this->getConnection()
                ->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('attachment'))
                ->set('deleted', ':deleted')
                ->where('id = :attachmentId')
                ->setParameter('deleted', false, Mapper::getParameterType(false))
                ->setParameter('attachmentId', $attachmentId)
                ->executeQuery();
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
        $this->addDependency('language');
    }
}
