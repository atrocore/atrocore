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
                "id"     => $type,
                "code"   => $type,
                "name"   => $this->translate($type, 'thumbnailTypes'),
                "width"  => $data["size"][0] ?? null,
                "height" => $data["size"][1] ?? null,
            ];
        }

        return $items;
    }

    public function insertEntity(Entity $entity): bool
    {
        if (!preg_match('/^[A-Za-z0-9]*$/', $entity->get('code'))) {
            throw new BadRequest("Code is invalid.");
        }

        if ($this->getMetadata()->get("app.thumbnailTypes.{$entity->get('code')}")) {
            throw new BadRequest("Thumbnail type '{$entity->get('code')}' is already exists.");
        }

        $entity->id = $entity->get('code');

        $this->getMetadata()->set('app', 'thumbnailTypes', [
            $entity->get('code') => [
                'size' => [
                    $entity->get('width'),
                    $entity->get('height'),
                ],
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
        $this->getMetadata()->set('app', 'thumbnailTypes', [
            $entity->get('code') => [
                'size' => [
                    $entity->get('width'),
                    $entity->get('height'),
                ],
            ],
        ]);

        $this->getMetadata()->save();

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

        return true;
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
