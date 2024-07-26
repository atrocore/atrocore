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

use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class NotificationRule extends Base
{
    protected array $ruleByOccurrences;
    public function findOneByOccurrence(string $occurrence, string $entityType): ?\Atro\Entities\NotificationRule
    {
        if(!empty($this->ruleByOccurrences[$occurrence][$entityType])) {
            return $this->ruleByOccurrences[$occurrence][$entityType];
        }

        $rule =  $this->where(["occurrence" => $occurrence, "entityType" => $entityType, 'isActive' => true])->findOne();

        if(!empty($rule)){
            return $this->ruleByOccurrences[$occurrence][$entityType] = $rule;
        }

        $rule =  $this->where(["occurrence" => $occurrence, "entityType" => '', 'isActive' => true])->findOne();

        if(!empty($rule)){
            return $this->ruleByOccurrences[$occurrence][$entityType] = $rule;
        }

    }
}
