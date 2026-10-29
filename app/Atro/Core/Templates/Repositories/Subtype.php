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

namespace Atro\Core\Templates\Repositories;

/**
 * Stores the type-specific data of a supertype record. The table has no "deleted" column: a subtype record
 * shares the lifecycle of its supertype record and is removed by the database together with it.
 */
class Subtype extends Base
{
    public function hasDeletedRecordsToClear(): bool
    {
        return false;
    }

    public function clearDeletedRecords(): void
    {
    }
}
