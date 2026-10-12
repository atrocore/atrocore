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

final readonly class DataLineageTreeDTO
{
    /**
     * @param DataLineageEndpointDTO[] $endpoints
     * @param DataLineageRecordDTO[]   $records
     * @param DataLineageLinkDTO[]     $links
     */
    public function __construct(
        public DataLineageEndpointDTO $endpoint,
        public array $endpoints,
        public array $records,
        public array $links,
        public bool $truncated
    ) {
    }

    public function toArray(): array
    {
        return [
            'endpoint'  => ['scope' => $this->endpoint->scope, 'id' => $this->endpoint->id],
            'endpoints' => array_map(fn(DataLineageEndpointDTO $item) => get_object_vars($item), $this->endpoints),
            'records'   => array_map(fn(DataLineageRecordDTO $item) => get_object_vars($item), $this->records),
            'links'     => array_map(fn(DataLineageLinkDTO $item) => get_object_vars($item), $this->links),
            'truncated' => $this->truncated,
        ];
    }
}
