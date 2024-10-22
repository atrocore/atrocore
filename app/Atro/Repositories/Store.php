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

use Atro\Core\ModuleManager\Manager as ModuleManager;
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class Store extends ReferenceData
{
    public function insertEntity(Entity $entity): bool
    {
        return false;
    }

    public function updateEntity(Entity $entity): bool
    {
        return false;
    }

    public function deleteEntity(Entity $entity): bool
    {
        return false;
    }

    protected function getAllItems(array $params = []): array
    {
        $contents = @file_get_contents('https://packagist.atrocore.com/store.json?id=' . $this->getConfig()->get('appId'));
        if (empty($contents)) {
            throw new \Error('Failed to retrieve data from the repository.');
        }

        $items = @json_decode($contents, true);
        if (empty($items)) {
            throw new \Error('Failed to retrieve data from the repository.');
        }

        // set status
        foreach ($items as $code => $item) {
            switch ($item['usage']) {
                case 'Public':
                    $items[$code]['status'] = 'available';
                    break;
                case 'Rent':
                case 'Purchase':
                    $items[$code]['status'] = !empty($item['expirationDate']) && $item['expirationDate'] >= date('Y-m-d') ? 'available' : 'buyable';
                    break;
                default:
                    $items[$code]['status'] = 'buyable';
            }

            if ($item['id'] === 'Atro') {
                $items[$code]['status'] = 'installed';
            } else {
                $module = $this->getModuleManager()->getModule($item['id']);
                if (!empty($module)) {
                    $items[$code]['status'] = 'installed';
                }
            }
        }

        // filter by status
        if (!empty($params['whereClause'][0]['status!='])) {
            foreach ($params['whereClause'][0]['status!='] as $status) {
                foreach ($items as $code => $row) {
                    if ($row['status'] === $status) {
                        unset($items[$code]);
                    }
                }
            }
        }

        return $items;
    }

    protected function getModuleManager(): ModuleManager
    {
        return $this->getInjection('moduleManager');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('moduleManager');
    }
}
