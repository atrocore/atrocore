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

class Matching extends ReferenceData
{

    public function getMatchedRecords(string $ruleCode, string $entityName, string $entityId): array
    {
        echo '<pre>';
        print_r('123');
        die();

        // $rule = $this
        //     ->getEntityManager()
        //     ->getRepository('Atro:MatchingRule')
        //     ->findOneBy(['code' => $ruleCode]);

        // if (!$rule) {
        //     return [];
        // }

        // $entity = $this
        //     ->getEntityManager()
        //     ->getRepository($entityName)
        //     ->find($entityId);

        // if (!$entity) {
        //     return [];
        // }

        // $matcher = $this->getMatcherFactory()->create($rule->getType());
        // $matcher->setRule($rule);
        // $matcher->setEntity($entity);

        // return $matcher->getMatches();
    }
}
