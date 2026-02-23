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
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class ThumbnailType extends ReferenceData
{
    protected function getAllItems(array $params = []): array
    {
        $items = [];

        foreach ($this->getMetadata()->get("app.thumbnailTypes") ?? [] as $type => $data) {
            $items[] = [
                "id"             => $type,
                "code"           => $type,
                "name"           => $this->translate($type, 'thumbnailTypes'),
                "width"          => $data["size"][0] ?? null,
                "height"         => $data["size"][1] ?? null,
                "deleteDisabled" => !empty($data["deleteDisabled"]),
                "createdAt"      => $data["createdAt"] ?? null,
                "createdById"    => $data["createdById"] ?? $this->getConfig()->get('systemUserId'),
                "modifiedAt"     => $data["modifiedAt"] ?? null,
                "modifiedById"   => $data["modifiedById"] ?? $this->getConfig()->get('systemUserId'),
            ];
        }

        return $items;
    }

    public function insertEntity(Entity $entity): bool
    {
        if (!preg_match('/^[A-Za-z0-9]*$/', $entity->get('code'))) {
            throw new BadRequest("Code is invalid.");
        }

        if (preg_match('/^\d+$/', $entity->get('code'))) {
            throw new BadRequest("Code must contains at least one letter.");
        }

        if ($this->getMetadata()->get("app.thumbnailTypes.{$entity->get('code')}")) {
            throw new BadRequest("Thumbnail type '{$entity->get('code')}' is already exists.");
        }

        $entity->id = $entity->get('code');

        $this->getMetadata()->set('app', 'thumbnailTypes', [
            $entity->get('code') => [
                'size'         => [
                    $entity->get('width'),
                    $entity->get('height'),
                ],
                'createdAt'    => date('Y-m-d H:i:s'),
                'createdById'  => $this->getEntityManager()->getUser()->id,
                'modifiedAt'   => date('Y-m-d H:i:s'),
                'modifiedById' => $this->getEntityManager()->getUser()->id,
            ],
        ]);

        $this->getMetadata()->save();

        $this->getLanguage()->set('Global', 'thumbnailTypes', $entity->get('code'), $entity->get('name'));
        $this->getLanguage()->save();

        if ($this->getLanguage()->getLanguage() !== $this->getBaseLanguage()->getLanguage()) {
            $this->getBaseLanguage()->set('Global', 'thumbnailTypes', $entity->get('code'), $entity->get('name'));
            $this->getBaseLanguage()->save();
        }

        return true;
    }

    public function updateEntity(Entity $entity): bool
    {
        if ($entity->isAttributeChanged('width') || $entity->isAttributeChanged('height')) {
            $this->getMetadata()->set('app', 'thumbnailTypes', [
                $entity->get('code') => [
                    'size'         => [
                        $entity->get('width'),
                        $entity->get('height'),
                    ],
                    'modifiedAt'   => date('Y-m-d H:i:s'),
                    'modifiedById' => $this->getEntityManager()->getUser()->id,
                ],
            ]);
            $this->getMetadata()->save();

            $this->deleteAllThumbnails();
        }

        if ($entity->isAttributeChanged('name')) {
            $this->getLanguage()->set('Global', 'thumbnailTypes', $entity->get('code'), $entity->get('name'));
            $this->getLanguage()->save();
        }

        return true;
    }

    public function deleteEntity(Entity $entity): bool
    {
        $this->getMetadata()->delete('app', 'thumbnailTypes', [$entity->get('code')]);
        $this->getMetadata()->save();

        $this->deleteAllThumbnails();

        return true;
    }

    public function deleteAllThumbnails(): void
    {
        $path = 'public/'.trim($this->getConfig()->get('thumbnailsPath', 'upload/thumbnails'), '/');
        if (is_dir($path)) {
            exec('rm -rf '.escapeshellarg($path));
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('baseLanguage');
    }

    protected function getBaseLanguage(): \Atro\Core\Utils\Language
    {
        return $this->getInjection('baseLanguage');
    }
}
