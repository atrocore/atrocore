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

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;

class JobEntity extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeSave(Event $event)
    {
        // prepare data
        $entity = $event->getArgument('entity');

        // set scheduledJobId to data
        if (!empty($scheduledJobId = $entity->get('scheduledJobId'))) {
            $entity->set('targetType', 'ScheduledJob');
            $entity->set('targetId', $scheduledJobId);
        }

        // skip saving for Stream action
        if ($entity->get('serviceName') == 'Stream' && $entity->get('methodName') == 'controlFollowersJob') {
            // for skip saving
            $entity->setIsSaved(true);

            // call service method
            $this->controlFollowersJob($entity->get('data'));
        }
    }

    /**
     * @param array $data
     */
    protected function controlFollowersJob(array $data): void
    {
        // prepare input
        $input = new \stdClass();
        $input->entityId = $data['entityId'];
        $input->entityType = $data['entityType'];

        $this->getContainer()->get('serviceFactory')->create('Stream')->controlFollowersJob($input);
    }
}
