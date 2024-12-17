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

namespace Atro\Jobs;

use Atro\Entities\Job;
use Atro\NotificationTransport\EmailTransport;

class SendEmail extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        if (empty($data['connectionId'])) {
            return;
        }

        $emailData = $data['emailData'] ?? [];
        $params = $data['params'] ?? [];

        $connectionEntity = $this->getEntityManager()->getEntity('Connection', $data['connectionId']);
        if (empty($connectionEntity) || empty($connectionEntity->id)) {
            $GLOBALS['log']->error("SMTP Connection entity not found : " . $data['connectionId']);
        }

        if (!empty($emailData['shouldBeRendered']) && !empty($emailData['notificationParams']) && !empty($emailData['subject']) && !empty($emailData['body'])) {
            $emailTransport = $this->getContainer()->get(EmailTransport::class);
            $emailData['subject'] = $emailTransport->renderTemplate($emailData['subject'], $emailData['notificationParams']);
            $emailData['body'] = $emailTransport->renderTemplate($emailData['body'], $emailData['notificationParams']);
            unset($emailData['notificationParams']);
            unset($emailData['shouldBeRendered']);
        }

        try {
            $this->getContainer()->get('mailSender')->send($emailData, $connectionEntity, $params);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('MailSender: [' . $e->getCode() . '] ' . $e->getMessage());
        }
    }
}
