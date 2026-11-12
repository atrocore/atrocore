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

namespace Atro\Entities;

use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

use Atro\Core\Templates\Entities\Base;
use Atro\Repositories\MatchingRule as MatchingRuleRepository;

class MatchingRule extends Base
{
    protected $entityType = "MatchingRule";

    public function prepareMatchSelect(QueryBuilder $qb, Entity $stageEntity): void
    {
        $this->getRepository()
            ->createMatchingType($this)
            ->prepareMatchSelect($qb, $stageEntity);
    }

    public function match(Entity $stageEntity, array $masterEntityData): float
    {
        return $this->getRepository()
            ->createMatchingType($this)
            ->match($stageEntity, $masterEntityData);
    }

    public function isSymmetric(): bool
    {
        return $this->getRepository()
            ->createMatchingType($this)
            ->isSymmetric();
    }

    public function getNestedRules(): iterable
    {
        return $this->get('matchingRules') ?? [];
    }

    public function getMatching(): ?Matching
    {
        return $this->getRepository()->getMatching($this);
    }

    public function getWeight(): int
    {
        return (int)$this->getRepository()
            ->createMatchingType($this)
            ->getWeight();
    }

    protected function getRepository(): MatchingRuleRepository
    {
        return $this->getEntityManager()->getRepository('MatchingRule');
    }
}
