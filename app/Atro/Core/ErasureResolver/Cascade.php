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

class Cascade extends AbstractErasureResolver
{
    public function supports(Entity $entity, string $field): bool
    {
        return in_array($this->getFieldDefs($entity, $field)['type'] ?? null, self::LINK_TYPES);
    }

    public function resolve(Entity $entity, string $field, array &$processed = []): bool
    {
        $foreignEntityType = $this->getForeignEntityType($entity, $field);
        if (empty($foreignEntityType) || empty($this->getMetadata()->get(['scopes', $foreignEntityType, 'containsPersonalData']))) {
            return false;
        }

        if ($this->getMetadata()->get(['scopes', $foreignEntityType, 'type']) === 'ReferenceData') {
            return false;
        }

        $repository = $this->getEntityManager()->getRepository($foreignEntityType);

        foreach ($this->getLinkedEntities($entity, $field) as $foreign) {
            $repository->erasePersonalData($foreign, $processed);
        }

        return true;
    }
}
