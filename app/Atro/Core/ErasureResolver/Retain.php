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

namespace Atro\Core\ErasureResolver;

use Espo\ORM\Entity;

class Retain extends AbstractErasureResolver
{
    public function supports(Entity $entity, string $field): bool
    {
        return true;
    }

    public function resolve(Entity $entity, string $field, array &$processed = []): bool
    {
        return false;
    }
}
