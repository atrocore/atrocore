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

namespace Atro\DTOs\Erasure;

use Espo\ORM\Entity;

/**
 * The data of the record whose personal data are erased.
 */
class ErasureDataDTO
{
    /**
     * @param bool $automatically whether the data are erased without a user action, e.g. by an "Erasure" action executed by a workflow or a scheduled job
     * @param array $processed records that are already erased, shared by the whole erasure, prevents endless loops for cascade erasure
     */
    public function __construct(
        public readonly Entity $entity,
        public readonly bool $automatically = false,
        private array &$processed = []
    ) {
    }

    /**
     * The data of the linked record within the same erasure
     */
    public function forEntity(Entity $entity): self
    {
        return new self($entity, $this->automatically, $this->processed);
    }

    public function isProcessed(): bool
    {
        return isset($this->processed[$this->getKey()]);
    }

    public function markProcessed(): void
    {
        $this->processed[$this->getKey()] = true;
    }

    protected function getKey(): string
    {
        return $this->entity->getEntityName() . '_' . $this->entity->get('id');
    }
}
