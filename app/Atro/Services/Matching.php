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
        sleep(5);

        return [
            [
                'id' => '1',
                'name' => 'Matched Record 1',
            ],
            [
                'id' => '2',
                'name' => 'Matched Record 2',
            ]
        ];
    }
}
