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

class QueueManagerEmailSender extends QueueManagerBase
{
    /**
     * @inheritdoc
     */
    public function run(array $data = []): bool
    {
        $emailData = !empty($data['emailData']) ? $data['emailData'] : [];
        $params = !empty($data['params']) ? $data['params'] : [];
        $connectionEntity = $this->getEntityManager()->getEntity('Connection', $data['connectionId']);

        if (empty($connectionEntity)) {
            throw new \Exception("Connection entity not found : " . $data['connectionId']);
        }

        try {
            $this->getContainer()->get('mailSender')->send($emailData, $connectionEntity, $params);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('MailSender: [' . $e->getCode() . '] ' . $e->getMessage());
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getNotificationMessage(Entity $queueItem): string
    {
        return '';
    }
}
