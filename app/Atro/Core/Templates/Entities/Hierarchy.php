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
    public function getParentId(bool $fromDbOnly = false): ?string
    {
        $parentId = null;

        if (!$fromDbOnly && !empty($parentsIds = $this->get('parentsIds'))) {
            $parentId = array_shift($parentsIds);
        } else {
            $parents = $this->get('parents');
            if (!empty($parents[0])) {
                $parentId = $parents[0]->get('id');
            }
        }

        return $parentId;
    }

    public function getParent(bool $fromDbOnly = false): ?Hierarchy
    {
        $parent = null;

        if (!$fromDbOnly && !empty($parentsIds = $this->get('parentsIds'))) {
            $parent = $this->getEntityManager()->getRepository($this->entityType)->get(array_shift($parentsIds));
        } else {
            $parents = $this->get('parents');
            if (!empty($parents[0])) {
                $parent = $parents[0];
            }
        }

        return $parent;
    }
}
