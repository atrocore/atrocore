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

namespace Atro\Core\Utils;

class DataUtil
{
    /**
     * Recursively converts stdClass objects (and any mixed nested structure) to plain arrays.
     */
    public static function toArray(mixed $data): array
    {
        return json_decode(json_encode($data), true) ?? [];
    }
}
