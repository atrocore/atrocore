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

use Atro\Core\MatchingRuleType\AbstractMatchingRule;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Entities\MatchingRule as EntitiesMatchingRule;

class MatchingRule extends ReferenceData
{
    public function createMatchingType(EntitiesMatchingRule $rule): AbstractMatchingRule
    {
        return $this->getInjection('matchingManager')->createMatchingType($rule);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('matchingManager');
    }
}
