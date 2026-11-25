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

namespace Atro\DTOs;

use Espo\ORM\Entity;

class ErasureDataDTO
{
    /**
     * Records that are already erased, prevents endless loops for cascade erasure.
     */
    private array $processed;

    /**
     * @param bool $automatically whether the data are erased without a user action, e.g. by an "Erasure" action executed by a workflow or a scheduled job
     */
    public function __construct(
        public readonly Entity $entity,
        public readonly string $field,
        array &$processed = [],
        public readonly bool $automatically = false
    ) {
        $this->processed = &$processed;
    }

    public function &getProcessed(): array
    {
        return $this->processed;
    }
}
