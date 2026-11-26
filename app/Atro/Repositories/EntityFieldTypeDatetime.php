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

namespace Atro\Repositories;

/**
 * The default value of a datetime field is a date with time.
 */
class EntityFieldTypeDatetime extends EntityFieldTypeDate
{
    protected const DEFAULT_VALUE_FORMAT = 'Y-m-d H:i:s';
}
