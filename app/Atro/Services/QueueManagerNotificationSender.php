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

use Atro\Core\Utils\NotificationManager;
use Atro\NotificationTransport\NotificationOccurrence;
use Espo\ORM\Entity;

class QueueManagerNotificationSender extends QueueManagerBase
{
    /**
     * @inheritdoc
     */
    public function run(array $data = []): bool
    {
        if(empty($data['occurrence']) || empty($data['entityId']) || empty($data['entityType']) || empty($data['actionUserId'])) {
            return true;
        }

        $occurrence = $data['occurrence'];

        $entity = $this->getEntityManager()
            ->getRepository($data['entityType'])
            ->where(['id' => $data['entityId']])
            ->findOne(['withDeleted' => in_array($occurrence, [NotificationOccurrence::DELETION, NotificationOccurrence::NOTE_DELETED])]);

        if(empty($entity)){
            return true;
        }

        $actionUser = $this->getEntityManager()
            ->getRepository('User')
            ->where(['id' =>$data['actionUserId']])
            ->findeOne();

        /** @var NotificationManager $notificationManager*/
        $notificationManager = $this->getInjection('notificationManager');
        $notificationManager->handleNotification($occurrence, $entity, $actionUser);

        return true;
    }
}
