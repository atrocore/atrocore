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

use Atro\Core\Templates\Services\ReferenceData;
use Atro\Entities\MatchingRule as MatchingRuleEntity;
use Espo\ORM\Entity;

class MatchingRule extends ReferenceData
{
    /**
     * @param MatchingRuleEntity $entity
     * @return void
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('weight', $entity->getWeight());
    }
}
