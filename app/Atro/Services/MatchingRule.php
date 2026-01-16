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

use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class MatchingRule extends Base
{
    protected $mandatorySelectAttributeList = ['matchingId', 'matchingRuleSetId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $checkEntity = $entity;
        while (true) {
            if (empty($checkEntity->get('matchingRuleSetId'))) {
                break;
            }
            $checkEntity = $this->getRepository()->get($checkEntity->get('matchingRuleSetId'));
        }

        $matching = $checkEntity->get('matching');
        if (!empty($matching)) {
            $entity->set('editable', $this->getAcl()->check($matching, 'edit'));
        }
    }
}
