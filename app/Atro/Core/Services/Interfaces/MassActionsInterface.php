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
 * The records of the entity can be updated, deleted and restored in bulk. Implemented by Atro\Core\Services\Traits\MassActionsTrait.
 */
interface MassActionsInterface
{
    public function massUpdate($data, array $params);

    public function massRestore(array $params);

    public function massRemove(array $params);
}
