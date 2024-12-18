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

use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity as OrmEntity;

class Entity extends ReferenceData
{
    protected function getAllItems(array $params = []): array
    {
        $items = [];
        foreach ($this->getMetadata()->get('scopes', []) as $code => $row) {
            if (in_array($code, ['Entity'])) {
                continue;
            }

            $items[] = array_merge($row, [
                'id'   => $code,
                'code' => $code,
                'name' => $row['name'] ?? $code
            ]);
        }

        return $items;
    }

    protected function saveDataToFile(array $data): bool
    {
        return true;
    }
}
