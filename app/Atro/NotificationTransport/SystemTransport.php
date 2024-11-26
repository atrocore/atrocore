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
        $this->addEntitiesAdditionalData($params, $this->getLanguage()->getLanguage());

        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set('type', 'Message');
        $notification->set('message', $this->getTwig()->renderTemplate($template->get('body'), $params));
        $notification->set('userId', $user->get('id'));
        $notification->set('relatedId', $params['entity']->get('id'));
        $notification->set('relatedType', $params['entity']->getEntityType());

        $this->getEntityManager()->saveEntity($notification);
    }
}
