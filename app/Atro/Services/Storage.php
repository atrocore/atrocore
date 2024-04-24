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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\Services\QueueManagerServiceInterface;

class Storage extends Base implements QueueManagerServiceInterface
{
    public function createScanJob(string $storageId, bool $manual): bool
    {
        $storage = $this->getEntity($storageId);
        if (empty($storage)) {
            throw new NotFound();
        }

        $name = $this->getInjection('language')->translate('scan', 'labels', 'Storage') . ' ' . $storage->get('name');

        return $this->getInjection('queueManager')->push($name, 'Storage', ['storageId' => $storage->get('id'), 'storageName' => $storage->get('name'), 'manual' => $manual]);
    }

    public function run(array $data = []): bool
    {
        $storage = $this->getEntity($data['storageId']);
        $this->getInjection('container')->get($storage->get('type') . 'Storage')->scan($storage);

        return true;
    }

    public function getNotificationMessage(Entity $queueItem): string
    {
        if (!$queueItem->get('data')->manual) {
            return '';
        }

        return sprintf($this->getInjection('language')->translate('scanDone', 'labels', 'Storage'), $queueItem->get('data')->storageName);
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('queueManager');
        $this->addDependency('container');
    }
}
