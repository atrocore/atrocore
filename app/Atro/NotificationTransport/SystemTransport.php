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

use Atro\Entities\NotificationTemplate;
use Atro\Core\Utils\Util;
use Espo\Entities\User;

class SystemTransport extends AbstractNotificationTransport
{

    public function send(User $user, NotificationTemplate $template, array $params): void
    {
        $data = [];
        $mainLanguageMessage  = $template->get('body');
        foreach ($this->getConfig()->get('locales') as $locale) {
            $field = 'body';
            $message = null;
            if ($locale['language'] !== $this->getConfig()->get('mainLanguage')) {
                $suffix = ucfirst(Util::toCamelCase(strtolower($locale['language'])));
                $field .= $suffix;
            }

            if (!empty($template->get($field))) {
                $this->addEntitiesAdditionalData($params, $locale['language']);
                $message = $this->getTwig()->renderTemplate($template->get($field), $params);
            }

            $data[$locale['language']] = $message ?? $mainLanguageMessage;
        }
        $notification = $this->getEntityManager()->getEntity('Notification');

        $notification->set('type', 'TranslatedMessage');
        $notification->set('data', $data);
        $notification->set('userId', $user->get('id'));
        $notification->set('relatedId', $params['entity']->get('id'));
        $notification->set('relatedType', $params['entity']->getEntityType());

        $this->getEntityManager()->saveEntity($notification);
    }
}