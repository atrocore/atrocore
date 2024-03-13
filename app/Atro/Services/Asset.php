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

use Atro\Core\AssetValidator;
use Atro\Core\Templates\Services\Hierarchy;
use Atro\Core\ConfigManager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Log;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Asset extends Hierarchy
{
    /**
     * @var string[]
     */
    protected $mandatorySelectAttributeList = ['fileId', 'type'];

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        if (!empty($collection[0])) {
            $attachments = $this->getEntityManager()
                ->getRepository('Attachment')
                ->where(['id' => array_column($collection->toArray(), 'fileId')])
                ->find(['withDeleted' => true]);

            foreach ($collection as $entity) {
                foreach ($attachments as $attachment) {
                    if ($attachment->get('id') === $entity->get('fileId')) {
                        $this->setAttachmentData($entity, $attachment);
                        continue 2;
                    }
                }
            }
        }
    }

    public function setAttachmentData(Entity $asset, Entity $attachment): void
    {
        $asset->_hasAttachmentData = true;

        $asset->set('icon', $this->prepareAssetIcon((string)$attachment->get('name')));
        $asset->set('private', $attachment->get('private'));
        $asset->set('hasOpen', false);

        $pathData = $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($attachment);
        if (!empty($pathData['download'])) {
            $asset->set('url', rtrim($this->getConfig()->get('siteUrl', ''), '/') . '/' . $pathData['download']);

            $nameParts = explode('.', $attachment->get('name'));
            $fileExt = array_pop($nameParts);

            $asset->set('hasOpen', in_array($fileExt, $this->getMetadata()->get('dam.image.extensions', [])) || $fileExt === 'pdf');
        }
    }

    /**
     * @inheritDoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (!property_exists($entity, '_hasAttachmentData')) {
            $file = $this->getEntityManager()->getRepository('Attachment')
                ->where(['id' => $entity->get('fileId')])
                ->findOne(['withDeleted' => true]);
            if (!empty($file)) {
                $this->setAttachmentData($entity, $file);
            }
        }
    }

    public function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $file = $entity->get('file');
        if (!empty($file)) {
            $entity->set('private', $file->get('private'));
        }
        return parent::isEntityUpdated($entity, $data);
    }

    public function recheckAssetTypes(array $data): void
    {
        if (empty($data['assetId'])) {
            return;
        }

        try {
            $asset = $this->getEntity($data['assetId']);
        } catch (\Throwable $e) {
            return;
        }

        $attachment = $asset->get('file');
        if (empty($attachment)) {
            return;
        }

        $typesToExclude = [];
        foreach ($asset->get('type') as $type) {
            try {
                $this->getInjection(AssetValidator::class)->validateViaType((string)$type, clone $attachment);
            } catch (\Throwable $e) {
                $typesToExclude[] = $type;
            }
        }

        if (!empty($typesToExclude)) {
            $filteredTypes = [];
            foreach ($asset->get('type') as $type) {
                if (!in_array($type, $typesToExclude)) {
                    $filteredTypes[] = $type;
                }
            }

            if (empty($filteredTypes)) {
                $filteredTypes = ['File'];
            }

            $asset->set('type', $filteredTypes);
            $this->getEntityManager()->saveEntity($asset, ['skipAll' => true]);
        }
    }

    /**
     * @inheritDoc
     */
    public function isRequiredField(string $field, Entity $entity, $typeResult): bool
    {
        if ($field == 'filesIds') {
            return false;
        }

        return parent::isRequiredField($field, $entity, $typeResult);
    }

    /**
     * @inheritDoc
     */
    public function createEntity($data)
    {
        if (property_exists($data, 'url')) {
            if (empty($data->url) || empty($attachment = $this->getService('Attachment')->createEntityByUrl($data->url, false))) {
                throw new BadRequest(sprintf($this->translate('wrongUrl', 'exceptions', 'Asset'), $data->url));
            }

            if (!empty($asset = $this->getEntityManager()->getRepository('Asset')->where(['fileId' => $attachment->get('id')])->findOne())) {
                $asset = $this->readEntity($asset->get('id'));
                if (property_exists($data, 'type') && $data->type !== $asset->get('type')) {
                    throw new BadRequest(sprintf($this->translate('assetExistWithOtherType', 'exceptions', 'Asset'), $data->url));
                }
                return $asset;
            }

            $data->name = $attachment->get('name');
            $data->fileId = $attachment->get('id');

            unset($data->url);
        }

        $entity = $this->getRepository()->get();
        $entity->set($data);

        // Are all required fields filled ?
        $this->checkRequiredFields($entity, $data);

        if ($this->isMassCreating($entity)) {
            return $this->massCreateAssets($data);
        }

        return parent::createEntity($data);
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $this->validateAttachment($entity, $data);
        parent::beforeCreateEntity($entity, $data);
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        $this->validateAttachment($entity, $data);
        parent::beforeUpdateEntity($entity, $data);
    }

    protected function validateAttachment($entity, $data)
    {
        if (!empty($entity->get('fileId')) && property_exists($data, 'type')) {
            $this->getInjection(AssetValidator::class)->validateViaTypes(
                $this->getTypeValue($data->type), $this->getEntityManager()->getEntity('Attachment', $entity->get('fileId'))
            );
        }
    }

    public function getTypeValue($type)
    {
        if (empty($type)) {
            return [];
        }

        $defs = $this->getMetadata()->get(['entityDefs', $this->entityName, 'fields', 'type'], []);
        $options = [];
        foreach ($type as $optionId) {
            $key = array_search($optionId, $defs['optionsIds']);
            if ($key === false) {
                // for create or massupload
                $options[] = $optionId;
            } else {
                // for update
                $options[] = $defs['options'][$key];
            }
        }
        return $options;
    }

    public function getFileInfo(Entity $asset)
    {
        $attachment = $this->getEntityManager()->getEntity('Attachment', $asset->get('fileId'));

        $fileInfo = $this->getService("Attachment")->getFileInfo($attachment);

        $asset->set(
            [
                "size"     => round($fileInfo['size'] / 1024, 1),
                "sizeUnit" => "kb",
            ]
        );

        if ($this->isImage($asset)) {
            $imageInfo = $this->getService("Attachment")->getImageInfo($attachment);
            $this->updateAttributes($asset, $imageInfo);
        }
    }

    public function updateAttributes(Entity $asset, array $imageInfo)
    {
        $asset->set(
            $this->attributeMapping("height"),
            ($imageInfo['height'] ?? false) ? $imageInfo['height'] : null
        );
        $asset->set(
            $this->attributeMapping("width"),
            ($imageInfo['width'] ?? false) ? $imageInfo['width'] : null
        );
        $asset->set(
            $this->attributeMapping("color-space"),
            ($imageInfo['color_space'] ?? false) ? $imageInfo['color_space'] : null
        );
        $asset->set(
            $this->attributeMapping("color-depth"),
            ($imageInfo['color_depth'] ?? false) ? $imageInfo['color_depth'] : null
        );
        $asset->set(
            $this->attributeMapping("orientation"),
            ($imageInfo['orientation'] ?? false) ? $imageInfo['orientation'] : null
        );
    }

    protected function massCreateAssets(\stdClass $data): Entity
    {
        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        $entity = $this->getRepository()->get();

        try {
            foreach ($data->filesIds as $fileId) {
                $fileName = $data->filesNames->$fileId;

                $postData = clone $data;
                $postData->fileId = $fileId;
                $postData->fileName = $fileName;
                $postData->name = $fileName;
                $postData->sorting = null;

                unset($postData->filesIds);
                unset($postData->filesNames);
                unset($postData->filesTypes);

                $entity = parent::createEntity($postData);
            }

            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw new BadRequest("{$fileName}: {$e->getMessage()}");
        }

        return $entity;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency("language");
        $this->addDependency("configManager");
        $this->addDependency('log');
        $this->addDependency('eventManager');
        $this->addDependency('queueManager');
        $this->addDependency(AssetValidator::class);
    }

    /**
     * @return ConfigManager
     */
    protected function getConfigManager(): ConfigManager
    {
        return $this->getInjection("configManager");
    }

    /**
     * @return Log
     */
    protected function getLog(): Log
    {
        return $this->getInjection("log");
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    protected function getService($name)
    {
        return $this->getServiceFactory()->create($name);
    }

    protected function attributeMapping(string $name): string
    {
        return $this->getConfigManager()->get(["attributeMapping", $name, "field"]) ?? $name;
    }

    protected function translate(string $label, string $category, string $scope): string
    {
        return $this->getInjection("language")->translate($label, $category, $scope);
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isMassCreating(Entity $entity): bool
    {
        return !empty($entity->get('filesIds')) && $entity->isNew();
    }

    protected function prepareAssetIcon(string $fileName): ?string
    {
        $fileNameParts = explode('.', $fileName);
        $fileExt = strtolower(array_pop($fileNameParts));

        return in_array($fileExt, $this->getMetadata()->get('fields.asset.hasPreviewExtensions', [])) ? null : $fileExt;
    }

    protected function isImage(Entity $asset): bool
    {
        $file = !empty($asset->get('file')) ? $asset->get('file') : $this->getEntityManager()->getEntity('Attachment', $asset->get('fileId'));

        if (empty($file)) {
            return false;
        }

        $fileNameParts = explode('.', $file->get('name'));
        $fileExt = strtolower(array_pop($fileNameParts));

        return in_array($fileExt, $this->getMetadata()->get('dam.image.extensions', []));
    }
}
