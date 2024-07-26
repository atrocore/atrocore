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
            ->findOne(['withDeleted' => true]);

        if(empty($entity)){
            return true;
        }

        $actionUser = $this->getEntityManager()->getEntity('User',$data['actionUserId']);

        /** @var NotificationManager $notificationManager*/
        $notificationManager = $this->getInjection('notificationManager');
        $notificationManager->process($occurrence, $entity, $actionUser);

        return true;
    }
}
