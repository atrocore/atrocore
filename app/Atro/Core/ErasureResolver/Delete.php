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

use Atro\DTOs\Erasure\ErasureFieldDataDTO;

class Delete extends AbstractErasureResolver
{
    public function supports(ErasureFieldDataDTO $data): bool
    {
        $fieldDefs = $this->getFieldDefs($data->entity, $data->field);
        if (!in_array($fieldDefs['type'] ?? null, self::LINK_TYPES)) {
            return false;
        }

        $foreignEntityType = $this->getForeignEntityType($data->entity, $data->field);

        return empty($foreignEntityType) || $this->getMetadata()->get(['scopes', $foreignEntityType, 'type']) !== 'Subtype';
    }

    public function resolve(ErasureFieldDataDTO $data): bool
    {
        $linkedEntities = $this->getLinkedEntities($data->entity, $data->field);

        $this->unlink($data->entity, $data->field, $linkedEntities);

        $foreignType = $this->getMetadata()->get(['scopes', $this->getForeignEntityType($data->entity, $data->field), 'type'], 'Base');

        foreach ($linkedEntities as $foreign) {
            $this->getEntityManager()->removeEntity($foreign);

            if ($foreignType !== 'ReferenceData') {
                $this->getEntityManager()->getRepository($foreign->getEntityName())->deleteFromDb($foreign->id);
            }
        }

        return true;
    }
}
