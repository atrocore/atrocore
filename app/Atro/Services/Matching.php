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

namespace Atro\Services;

use Atro\Core\Templates\Services\ReferenceData;

class Matching extends ReferenceData
{
    public function getMatchedRecords(string $ruleCode, string $entityName, string $entityId): array
    {
        return [
            'entityName' => 'Product',
            'list' => [
                [
                    'id' => '123-456-789',
                    'name' => 'Sample Product'
                ],
                [
                    'id' => '987-654-321',
                    'name' => 'Another Product'
                ],
            ],
        ];
    }
}
