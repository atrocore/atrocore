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

namespace Atro\Core\Services\Interfaces;

/**
 * The records of the entity can be created and updated. Implemented by Atro\Core\Services\Traits\EditableTrait.
 */
interface EditableInterface
{
    public function createEntity(\stdClass $attachment): string;

    public function updateEntity(string $id, \stdClass $data): bool;

    public function getDuplicateAttributes($id);
}
