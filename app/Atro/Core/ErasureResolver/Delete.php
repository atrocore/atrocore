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

namespace Atro\Core\ErasureResolver;

use Espo\ORM\Entity;

class Delete extends AbstractErasureResolver
{
    public function supports(Entity $entity, string $field): bool
    {
        $fieldDefs = $this->getFieldDefs($entity, $field);
        if (!in_array($fieldDefs['type'] ?? null, self::LINK_TYPES)) {
            return false;
        }

        $foreignEntityType = $this->getForeignEntityType($entity, $field);

        return empty($foreignEntityType) || $this->getMetadata()->get(['scopes', $foreignEntityType, 'type']) !== 'Subtype';
    }

    public function resolve(Entity $entity, string $field, array &$processed = []): bool
    {
        $linkedEntities = $this->getLinkedEntities($entity, $field);

        $this->unlink($entity, $field, $linkedEntities);

        $foreignType = $this->getMetadata()->get(['scopes', $this->getForeignEntityType($entity, $field), 'type'], 'Base');

        foreach ($linkedEntities as $foreign) {
            $this->getEntityManager()->removeEntity($foreign);

            if ($foreignType !== 'ReferenceData') {
                $this->getEntityManager()->getRepository($foreign->getEntityName())->deleteFromDb($foreign->id);
            }
        }

        return true;
    }
}
