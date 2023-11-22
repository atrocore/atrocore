<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Templates\Repositories;

use Espo\Core\ORM\Repositories\RDB;

class Relation extends RDB
{
    public static function buildVirtualFieldName(string $relationName, string $fieldName): string
    {
        return "rel_{$relationName}_{$fieldName}";
    }

    public static function isVirtualRelationField(string $fieldName): array
    {
        if (preg_match_all('/^rel\_(.*)\_(.*)$/', $fieldName, $matches)) {
            return [
                'relationName' => $matches[1][0],
                'fieldName'    => $matches[2][0]
            ];
        }
        return [];
    }
}
