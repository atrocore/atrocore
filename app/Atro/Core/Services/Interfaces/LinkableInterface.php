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
 * The records of the entity can be linked to and unlinked from other records. Implemented by Atro\Core\Services\Traits\LinkableTrait.
 */
interface LinkableInterface
{
    public function linkEntity($id, $link, $foreignId);

    public function duplicateAndLinkEntity($id, $link, $foreignId): bool;

    public function unlinkEntity($id, $link, $foreignId);

    public function linkEntityMass($id, $link, $where, $selectData = null);

    public function duplicateAndLinkEntityMass($id, $link, $where, $selectData = null);

    public function unlinkAll(string $id, string $link): bool;

    public function handleLinkEntitiesErrors(string $id, string $link, bool $shouldDuplicateForeign): void;

    public function addRelation(array $ids, array $foreignIds, string $link, ?array $relationData = null): array;

    public function removeRelation(array $ids, array $foreignIds, string $link, ?array $relationData = null): array;
}
