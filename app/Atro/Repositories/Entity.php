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

class Entity extends ReferenceData
{
    protected function getAllItems(array $params = []): array
    {
        $items = [];
        foreach ($this->getMetadata()->get('scopes', []) as $code => $row) {
            if (empty($row['type']) || in_array($code, ['Entity'])) {
                continue;
            }
            $items[] = array_merge($row, [
                'id'          => $code,
                'code'        => $code,
                'label'       => $this->getInjection('language')->translate($code, 'scopeNames'),
                'labelPlural' => $this->getInjection('language')->translate($code, 'scopeNamesPlural')
            ]);
        }

        return $items;
    }

    protected function saveDataToFile(array $data): bool
    {
        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
