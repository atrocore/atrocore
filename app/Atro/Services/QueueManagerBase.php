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

use Atro\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\Services\Base;
use Espo\ORM\Entity;
use Atro\Entities\QueueItem;

abstract class QueueManagerBase extends AbstractService implements QueueManagerServiceInterface
{
    protected QueueItem $qmItem;

    /**
     * @inheritDoc
     */
    public function getNotificationMessage(Entity $queueItem): string
    {
        return sprintf($this->translate('queueItemDone', 'notificationMessages', 'QueueItem'), $queueItem->get('name'), $queueItem->get('status'));
    }

    public function setQueueItem(QueueItem $qmItem): void
    {
        $this->qmItem = $qmItem;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    /**
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }

    /**
     * @param string $label
     * @param string $category
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $label, string $category = 'labels', string $scope = 'Global'): string
    {
        return $this->getInjection('language')->translate($label, $category, $scope);
    }

    public static function updatePublicData(string $massAction, string $entityType, ?array $data): void
    {
        $publicData = DataManager::getPublicData($massAction);
        if (empty($publicData)) {
            $publicData = [];
        }
        $publicData[$entityType] = $data;
        DataManager::pushPublicData($massAction, $publicData);
    }
}
