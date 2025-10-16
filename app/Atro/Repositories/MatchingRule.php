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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\MatchingRuleType\AbstractMatchingRule;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Entities\MatchingRule as EntitiesMatchingRule;
use Espo\ORM\Entity as OrmEntity;
use Espo\ORM\EntityCollection;

class MatchingRule extends ReferenceData
{
    public function validateCode(OrmEntity $entity): void
    {
        parent::validateCode($entity);

        if (!preg_match('/^[A-Za-z0-9_]*$/', $entity->get('code'))) {
            throw new BadRequest($this->translate('notValidCode', 'exceptions', 'Matching'));
        }
    }

    public function findRelated(OrmEntity $entity, string $link, array $selectParams): EntityCollection
    {
        if ($link === 'matchingRules') {
            $selectParams['whereClause'] = [['matchingRuleSetId=' => $entity->get('id')]];

            return $this->getEntityManager()->getRepository('MatchingRule')->find($selectParams);
        }

        return parent::findRelated($entity, $link, $selectParams);
    }

    public function countRelated(OrmEntity $entity, string $relationName, array $params = []): int
    {
        if ($relationName === 'matchingRules') {
            $params['offset'] = 0;
            $params['limit'] = \PHP_INT_MAX;

            return count($this->findRelated($entity, $relationName, $params));
        }

        return parent::countRelated($entity, $relationName, $params);
    }

    public function createMatchingType(EntitiesMatchingRule $rule): AbstractMatchingRule
    {
        return $this->getInjection('matchingManager')->createMatchingType($rule);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('matchingManager');
    }

    protected function getAllItems(array $params = []): array
    {
        $items = parent::getAllItems($params);
        foreach ($items as &$item) {
            /** @var \Atro\Entities\MatchingRule $entity */
            $entity = $this->entityFactory->create($this->entityName);
            $entity->set($item);
            $entity->setAsFetched();

            $item['weight'] = $entity->getWeight();

            if (!array_key_exists('matchingRuleSetId', $item)) {
                $item['matchingRuleSetId'] = null;
            }
        }
        unset($item);

        return $items;
    }

    protected function afterSave(OrmEntity $entity, array $options = []): void
    {
        parent::afterSave($entity, $options);

        $matching = $entity->get('matching');
        if (!empty($matching)) {
            $this->getMatchingRepository()->unmarkAllMatchingSearched($matching);
        }
    }

    protected function beforeRemove(OrmEntity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        if ($entity->get('type') === 'set') {
            foreach ($entity->get('matchingRules') ?? [] as $rule) {
                $this->getEntityManager()->removeEntity($rule);
            }
        }
    }

    protected function afterRemove(OrmEntity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $matching = $entity->get('matching');
        if (!empty($matching)) {
            $this->getMatchingRepository()->unmarkAllMatchingSearched($matching);
        }
    }

    protected function getMatchingRepository(): Matching
    {
        return $this->getEntityManager()->getRepository('Matching');
    }
}
