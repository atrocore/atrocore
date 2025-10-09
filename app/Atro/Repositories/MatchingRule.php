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

class MatchingRule extends ReferenceData
{
    public function validateCode(OrmEntity $entity): void
    {
        parent::validateCode($entity);

        if (!preg_match('/^[A-Za-z0-9_]*$/', $entity->get('code'))) {
            throw new BadRequest($this->translate('notValidCode', 'exceptions', 'Matching'));
        }
    }

    public function afterSave(OrmEntity $entity, array $options = []): void
    {
        parent::afterSave($entity, $options);

        $matching = $entity->get('matching');
        if (!empty($matching)) {
            $this->getMatchingRepository()->unmarkAllMatchingSearched($matching);
        }
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

    protected function getMatchingRepository(): Matching
    {
        return $this->getEntityManager()->getRepository('Matching');
    }
}
