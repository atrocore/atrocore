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
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Metadata;
use Atro\Entities\Matching as MatchingEntity;
use Atro\Entities\MatchingRule as MatchingRuleEntity;
use Espo\ORM\Entity as OrmEntity;

class MatchingRule extends Base
{
    /**
     * @param MatchingRuleEntity $entity
     * @param array              $options
     *
     * @return void
     * @throws BadRequest
     */
    protected function beforeSave(OrmEntity $entity, array $options = [])
    {
        if (!empty($entity->get('matchingId')) && empty($this->getEntityManager()->getRepository('Matching')->get($entity->get('matchingId')))) {
            throw new BadRequest($this->getInjection('language')->translate('notValidMatching', 'exceptions', 'MatchingRule'));
        }

        if (!empty($entity->get('matchingRuleSetId'))) {
            $set = $this->getEntityManager()->getRepository('MatchingRule')->get($entity->get('matchingRuleSetId'));
            if (empty($set) || $set->get('type') !== 'set') {
                throw new BadRequest($this->getInjection('language')->translate('notValidMatchingRuleSet', 'exceptions', 'MatchingRule'));
            }
        }

        $this->validateIsMatchingActive($entity);
        $this->validateFieldType($entity);

        parent::beforeSave($entity, $options);
    }

    public function getMatching(MatchingRuleEntity $rule): ?MatchingEntity
    {
        while (true) {
            $matchingRule = null;
            if (!empty($rule->get('matchingRuleSetId'))) {
                $matchingRule = $this->getEntityManager()->getRepository('MatchingRule')->get($rule->get('matchingRuleSetId'));
            }
            if (!empty($matchingRule)) {
                $rule = $matchingRule;
            } else {
                break;
            }
        }

        return $this->getEntityManager()->getRepository('Matching')->get($rule->get('matchingId'));
    }

    public function validateIsMatchingActive(MatchingRuleEntity $entity): void
    {
        $matching = $this->getMatching($entity);
        if (!empty($matching) && !empty($matching->get('isActive'))) {
            throw new BadRequest($this->getInjection('language')->translate('notValidMatchingActivation', 'exceptions', 'MatchingRule'));
        }
    }

    public function createMatchingType(MatchingRuleEntity $rule): AbstractMatchingRule
    {
        return $this->getInjection('matchingManager')->createMatchingType($rule);
    }

    protected function validateFieldType(MatchingRuleEntity $entity): void
    {
        $type = $entity->get('type');
        if (empty($type) || $type === 'set') {
            return;
        }

        $className = $this->getInjection('metadata')->get(['app', 'matchingRuleTypes', $type, 'className']);
        if (!$className || !class_exists($className)) {
            return;
        }

        /** @var AbstractMatchingRule $className */
        $supportedTypes = $className::getSupportedFieldTypes();

        if (!empty($entity->get('attributeId'))) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));
            if ($attribute && !in_array($attribute->get('type'), $supportedTypes)) {
                throw new BadRequest($this->getInjection('language')->translate('notValidAttributeType', 'exceptions', 'MatchingRule'));
            }
            return;
        }

        $field = $entity->get('field');
        if (empty($field)) {
            return;
        }

        $matching = $this->getMatching($entity);
        if (empty($matching)) {
            return;
        }

        $entityName = $matching->get('masterEntity');
        $fieldType  = $this->getInjection('metadata')->get(['entityDefs', $entityName, 'fields', $field, 'type']);
        if (empty($fieldType)) {
            return;
        }

        if (!in_array($fieldType, $supportedTypes)) {
            throw new BadRequest($this->getInjection('language')->translate('notValidFieldType', 'exceptions', 'MatchingRule'));
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('matchingManager');
        $this->addDependency('language');
        $this->addDependency('metadata');
    }

    /**
     * @param MatchingRuleEntity $entity
     * @param array              $options
     *
     * @return void
     */
    protected function afterSave(OrmEntity $entity, array $options = []): void
    {
        parent::afterSave($entity, $options);

        $this->recalculateWeightForSets();
    }

    /**
     * @param MatchingRuleEntity $entity
     * @param array              $options
     *
     * @return void
     */
    protected function beforeRemove(OrmEntity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        if ($entity->get('type') === 'set') {
            foreach ($entity->get('matchingRules') ?? [] as $rule) {
                $this->getEntityManager()->removeEntity($rule);
            }
        }
    }

    /**
     * @param MatchingRuleEntity $entity
     * @param array              $options
     *
     * @return void
     */
    protected function afterRemove(OrmEntity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->recalculateWeightForSets();
    }

    protected function recalculateWeightForSets(): void
    {
        foreach ($this->find() as $rule) {
            if ($rule->get('type') === 'set') {
                $ruleWeight = (int)$this->createMatchingType($rule)->getWeight();
                if ($rule->get('weight') !== $ruleWeight) {
                    $rule->set('weight', $ruleWeight);
                    $this->getEntityManager()->saveEntity($rule);
                }
            }
        }
    }

    protected function getMatchingRepository(): Matching
    {
        return $this->getEntityManager()->getRepository('Matching');
    }
}
