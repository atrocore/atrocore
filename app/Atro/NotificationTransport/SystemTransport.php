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
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Util;

class SystemTransport extends AbstractNotificationTransport
{

    public function send(\Espo\Entities\User $user, NotificationTemplate $template, array $params): void
    {
        $data = [];
        foreach ($this->getConfig()->get('locales') as $locale) {
            $message = null;
            if ($locale['language'] !== $this->getConfig()->get('mainLanguage')) {
                $suffix = ucfirst(Util::toCamelCase(strtolower($locale['language'])));
                if (!empty($template->get('body' . $suffix))) {
                    $message = $this->getTwig()->renderTemplate($template->get('body' . $suffix), $params);
                }
            }
            $data[$locale['language']] = $message;
        }
        $notification = $this->getEntityManager()->getEntity('Notification');

        $notification->set('type', 'Simple');
        $notification->set('data', $data);
        $notification->set('userId', $user->get('id'));

        $this->getEntityManager()->saveEntity($notification);
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }
}