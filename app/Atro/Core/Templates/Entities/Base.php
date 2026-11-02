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

class Base extends Entity
{
    /**
     * The record of the subtype this record belongs to, e.g. $exportFeed->getSubtype()->get('amount').
     */
    public function getSubtype(): ?Entity
    {
        return $this->entityManager->getRepository($this->getEntityName())->getSubtypeRecord($this);
    }
}
