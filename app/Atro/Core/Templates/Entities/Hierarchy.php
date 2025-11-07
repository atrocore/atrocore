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

namespace Atro\Core\Templates\Entities;

use Espo\Core\ORM\Entity;

class Hierarchy extends Entity
{
    public function getRoutes(): array
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getRoutes($this);
    }

    public function getParentId(): ?string
    {
        $ids = $this->getRoutes()[0] ?? [];

        return empty($ids) ? null : array_pop($ids);
    }

    public function getParent(): ?Hierarchy
    {
        $parentId = $this->getParentId();

        return empty($parentId) ? null : $this->getEntityManager()->getRepository($this->entityType)->get($parentId);
    }
}
