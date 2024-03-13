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

namespace Atro\ORM\DB;

use Espo\ORM\IEntity;

interface MapperInterface
{
    public function selectById(IEntity $entity, string $id, $params = []): ?IEntity;

    public function select(IEntity $entity, array $params): array;

    public function count(IEntity $entity, array $params = []): int;

    public function selectRelated(IEntity $entity, string $relationName, array $params = [], bool $totalCount = false);

    public function countRelated(IEntity $entity, string $relName, array $params): int;

    public function insert(IEntity $entity, bool $ignoreDuplicate = false): bool;

    public function update(IEntity $entity): bool;

    public function delete(IEntity $entity): bool;
}