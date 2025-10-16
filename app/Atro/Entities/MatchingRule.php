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

use Atro\Core\Templates\Entities\ReferenceData;

class MatchingRule extends ReferenceData
{
    protected $entityType = "MatchingRule";

    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        return $this->getEntityManager()->getRepository('MatchingRule')
            ->createMatchingType($this)
            ->prepareMatchingSqlPart($qb, $stageEntity);
    }

    public function match(Entity $stageEntity, array $masterEntityData): int
    {
        return $this->getEntityManager()->getRepository('MatchingRule')
            ->createMatchingType($this)
            ->match($stageEntity, $masterEntityData);
    }

    public function getWeight(): int
    {
        return $this->getEntityManager()->getRepository('MatchingRule')
            ->createMatchingType($this)
            ->getWeight();
    }
}
