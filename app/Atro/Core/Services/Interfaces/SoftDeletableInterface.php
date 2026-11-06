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
 * The deleted records of the entity can be restored or deleted permanently. Implemented by Atro\Core\Services\Traits\SoftDeletableTrait.
 */
interface SoftDeletableInterface
{
    public function restoreEntity($id);

    public function deleteEntityPermanently(string $id): bool;
}
