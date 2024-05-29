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
    public function getParentId(): ?string
    {
        $parentsIds = $this->get('parentsIds');
        if (is_array($parentsIds)) {
            return $parentsIds[0] ?? null;
        }

        $parents = $this->get('parents');

        return !empty($parents[0]) ? $parents[0]->get('id') : null;
    }

    public function getParent(): ?Hierarchy
    {
        $parentsIds = $this->get('parentsIds');
        if (is_array($parentsIds)) {
            return !empty($parentsIds[0]) ? $this->getEntityManager()->getRepository($this->entityType)->get($parentsIds[0]) : null;
        }

        $parents = $this->get('parents');

        return !empty($parents[0]) ? $parents[0] : null;
    }
}
