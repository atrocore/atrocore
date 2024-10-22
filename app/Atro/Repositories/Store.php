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

class Store extends ReferenceData
{
    public function insertEntity(Entity $entity): bool
    {
        return false;
    }

    public function updateEntity(Entity $entity): bool
    {
        return false;
    }

    public function deleteEntity(Entity $entity): bool
    {
        return false;
    }

    protected function getAllItems(): array
    {
        $items = [];

        $items['core'] = [
            'id'          => 'core',
            'name'        => 'Core',
            'code'        => 'atrocore/core',
            'description' => "",
            'url'         => 'git@gitlab.atrocore.com:atrocore/amazon-adapter.git',
            'status'      => 'available',
            'versions'    => [],
            'tags'        => []
        ];

        return $items;
    }
}
