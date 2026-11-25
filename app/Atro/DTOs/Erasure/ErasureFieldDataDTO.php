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
 * The data of the field of the record whose personal data are erased.
 */
class ErasureFieldDataDTO
{
    public readonly Entity $entity;

    public function __construct(
        public readonly ErasureDataDTO $erasure,
        public readonly string $field
    ) {
        $this->entity = $erasure->entity;
    }
}
