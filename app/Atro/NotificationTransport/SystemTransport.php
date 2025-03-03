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

namespace Atro\NotificationTransport;

use Espo\Entities\User;
use Espo\ORM\Entity;

class SystemTransport extends AbstractNotificationTransport
{
    public function send(User $user, Entity $template, array $params): void
    {
        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set('type', 'SystemNotification');
        $notification->set('userId', $user->get('id'));
        $notification->set('relatedId', $params['entity']->get('id'));
        $notification->set('relatedType', $params['entity']->getEntityType());

        $this->addEntitiesAdditionalData($params, $this->getLanguage()->getLanguage(), true);

        $notification->set('data', [
            "notificationTemplateId" => $template->get('id'),
            "notificationParams" => $params,
        ]);

        $this->getEntityManager()->saveEntity($notification);
    }
}
