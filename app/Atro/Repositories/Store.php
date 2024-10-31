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
use Atro\Services\Composer;
use Espo\ORM\Entity;

class Store extends ReferenceData
{
    private ?array $remoteItems = null;

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
        if ($this->remoteItems === null) {
            $cacheFile = 'data/store-cache.json';

            $contents = @file_get_contents('https://packagist.atrocore.com/store.json?id=' . $this->getConfig()->get('appId'));
            if (empty($contents)) {
                if (!file_exists($cacheFile)) {
                    throw new \Error('Failed to retrieve data from the repository.');
                } else {
                    $contents = file_get_contents($cacheFile);
                }
            }

            file_put_contents($cacheFile, $contents);

            $remoteItems = @json_decode($contents, true);
            if (empty($remoteItems)) {
                throw new \Error('Failed to retrieve data from the repository.');
            }

            $composerData = Composer::getComposerJson();

            // set status
            foreach ($remoteItems as $code => $item) {
                switch ($item['usage']) {
                    case 'Public':
                        $remoteItems[$code]['status'] = 'available';
                        break;
                    case 'Rent':
                    case 'Purchase':
                        $remoteItems[$code]['status'] = !empty($item['expirationDate']) && $item['expirationDate'] >= date('Y-m-d') ? 'available' : 'buyable';
                        break;
                    default:
                        $remoteItems[$code]['status'] = 'buyable';
                }
                if (!empty($composerData['require'][$code]) || !empty($this->getModuleManager()->getModule($item['id']))) {
                    $remoteItems[$code]['status'] = 'installed';
                }
            }

            $this->remoteItems = $remoteItems;
        }

        $items = $this->remoteItems;

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

        if (!empty($params['whereClause'][0]['status='])) {
            foreach ($params['whereClause'][0]['status='] as $status) {
                foreach ($items as $code => $row) {
                    if ($row['status'] !== $status) {
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
