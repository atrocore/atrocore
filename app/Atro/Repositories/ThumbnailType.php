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
                "name"   => $this->getLanguage()->translateOption($type, 'previewSize', 'EntityField'),
                "width"  => $data["size"][0] ?? null,
                "height" => $data["size"][1] ?? null,
            ];
        }

        return $items;
    }

    public function insertEntity(Entity $entity): bool
    {
        return true;
    }

    public function updateEntity(Entity $entity): bool
    {
        return true;
    }

    public function deleteEntity(Entity $entity): bool
    {
        return true;
    }
}
