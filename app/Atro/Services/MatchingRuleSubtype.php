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

use Atro\Core\Templates\Services\Subtype;
use Espo\ORM\Entity;

class MatchingRuleSubtype extends Subtype
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $supertype = $this->getMetadata()->get(['scopes', $this->entityType, 'supertypeId']);

        $matching = $this->getEntityManager()->getRepository($supertype)->getMatching($entity);
        if (empty($matching)) {
            return;
        }

        $entity->set('editable', $this->getAcl()->check($matching, 'edit'));

        if (!empty($entity->get('attributeId'))) {
            $this->getServiceFactory()->create($supertype)->putAttributesToMetadata($matching, $entity);
        }

        if ($entity->hasAttribute('fieldDefs')) {
            $entity->set('fieldDefs', $this->getMetadata()->get("entityDefs.{$matching->get('entity')}.fields.{$entity->get('field')}"));
        }
    }
}
