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

use Atro\Core\Container;
use Atro\Core\Mail\Sender;
use Espo\Entities\User;
use Espo\ORM\Entity;

class EmailTransport extends AbstractNotificationTransport
{
    protected Sender $sender;

    public function __construct(Container $container, Sender $sender)
    {
        $this->sender = $sender;
        parent::__construct($container);
    }

    public function send(User $user, Entity $template, array $params): void
    {
        if (empty($this->getConfig()->get('notificationSmtpConnectionId'))) {
            return;
        }

        if (empty($user->get('emailAddress'))) {
            return;
        }

        $language = $this->getUserLanguage($user);
        $this->addEntitiesAdditionalData($params, $language, true);

        $subject = $template->get('subject') ?? '';
        $body = $template->get('body') ?? '';

        $data = [
            'to'      => $user->get('emailAddress'),
            'subject' => $subject,
            'body'    => $body,
            'notificationParams' => $params,
            'shouldBeRendered' => true,
            'isHtml'  => $template->get('isHtml') ?? true
        ];

        $this->sender->sendByJob($data);
    }
}