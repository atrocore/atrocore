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

namespace Atro\Core\AnonymizationResolvers;

use Espo\ORM\Entity;

class Delete extends AbstractAnonymizationResolver
{
    public function supports(Entity $entity, string $field): bool
    {
        $fieldDefs = $this->getFieldDefs($entity, $field);

        return in_array($fieldDefs['type'] ?? null, self::LINK_TYPES);
    }

    public function resolve(Entity $entity, string $field, array &$processed = []): bool
    {
        $linkedEntities = $this->getLinkedEntities($entity, $field);

        $this->unlink($entity, $field, $linkedEntities);

        foreach ($linkedEntities as $foreign) {
            $this->getEntityManager()->removeEntity($foreign);
            $this->getEntityManager()->getRepository($foreign->getEntityName())->deleteFromDb($foreign->id);
        }

        return true;
    }
}
