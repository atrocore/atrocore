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

use Espo\ORM\Entity;

class MassDelete extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        if (empty($data['entityType']) || empty($data['total']) || empty($data['ids'])) {
            return false;
        }

        $entityType = $data['entityType'];
        $service = $this->getContainer()->get('serviceFactory')->create($entityType);

        $method = 'deleteEntity';
        if (!empty($data['deletePermanently'])) {
            $method = 'deleteEntityPermanently';
        }

        foreach ($data['ids'] as $id) {
            try {
                $service->$method($id);
            } catch (\Throwable $e) {
                $message = "MassDelete {$entityType} '$id', failed: {$e->getMessage()}";
                $GLOBALS['log']->error($message);
                $this->notify($message);
            }
        }

        return true;
    }

    public function getNotificationMessage(Entity $queueItem): string
    {
        return '';
    }


    protected function notify(string $message): void
    {
        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set('type', 'Message');
        $notification->set('message', $message);
        $notification->set('userId', $this->getUser()->get('id'));
        $this->getEntityManager()->saveEntity($notification);
    }
}
