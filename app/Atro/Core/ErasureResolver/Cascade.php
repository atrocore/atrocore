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

use Atro\DTOs\ErasureDataDTO;

class Cascade extends AbstractErasureResolver
{
    public function supports(ErasureDataDTO $data): bool
    {
        return in_array($this->getFieldDefs($data->entity, $data->field)['type'] ?? null, self::LINK_TYPES);
    }

    public function resolve(ErasureDataDTO $data): bool
    {
        $foreignEntityType = $this->getForeignEntityType($data->entity, $data->field);
        if (empty($foreignEntityType) || empty($this->getMetadata()->get(['scopes', $foreignEntityType, 'containsPersonalData']))) {
            return false;
        }

        if (in_array($this->getMetadata()->get(['scopes', $foreignEntityType, 'type']), ['ReferenceData', 'Archive'])) {
            return false;
        }

        $repository = $this->getEntityManager()->getRepository($foreignEntityType);
        $processed = &$data->getProcessed();

        foreach ($this->getLinkedEntities($data->entity, $data->field) as $foreign) {
            $repository->erasePersonalData($foreign, $processed, $data->automatically);
        }

        return true;
    }
}
