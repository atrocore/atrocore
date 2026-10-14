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

namespace Atro\DTOs\DataLineage;

final readonly class DataLineageRecordEdgeDTO
{
    public function __construct(
        public string $type,
        public string $scope,
        public string $id,
        public string $toScope,
        public string $toId
    ) {
    }

    public function getKey(): string
    {
        return "$this->scope:$this->id";
    }

    public function getToKey(): string
    {
        return "$this->toScope:$this->toId";
    }
}
