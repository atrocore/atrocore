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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Base;
use Atro\Repositories\BlockingRule as BlockingRuleRepository;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class BlockingRule extends Base
{
    protected $entityType = "BlockingRule";

    public function prepareSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        return $this->getRepository()
            ->createBlockingType($this)
            ->prepareSqlPart($qb, $stageEntity);
    }

    public function getNestedRules(): iterable
    {
        return $this->get('blockingRules') ?? [];
    }

    public function getMatching(): ?Matching
    {
        return $this->getRepository()->getMatching($this);
    }

    protected function getRepository(): BlockingRuleRepository
    {
        return $this->getEntityManager()->getRepository('BlockingRule');
    }
}
