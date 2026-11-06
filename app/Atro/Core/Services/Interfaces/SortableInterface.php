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
 * The records of the entity and its related records can be sorted manually. Implemented by Atro\Core\Services\Traits\SortableTrait.
 */
interface SortableInterface
{
    public function updateRelationSortOrder(string $id, string $link, array $ids, int $offset = 0): bool;

    public function updateSortOrder(array $ids, int $offset = 0): bool;
}
