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

final readonly class DataLineageLinkDTO
{
    public const string TYPE_DATA_PIPELINE = 'dataPipeline';
    public const string TYPE_CONSOLIDATION = 'consolidation';

    public function __construct(
        public string $type,
        public string $from,
        public string $to,
        public ?string $id,
        public ?int $number
    ) {
    }
}
