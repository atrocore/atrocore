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

use Atro\ActionTypes\TypeInterface;
use Atro\Entities\Job;
use Espo\Services\Record;

class ActionHandler extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        $action = $this->getEntityManager()->getRepository('Action')->get($data['actionId']);

        if (empty($action->get('sourceEntity'))) {
            return;
        }
        if (!empty($data['sourceEntity'])) {
            $action->set('sourceEntity', $data['sourceEntity']);
        }

        /** @var TypeInterface $actionType */
        $actionType = $this->getContainer()->get($this->getMetadata()->get(['action', 'types', $action->get('type')]));

        /** @var Record $service */
        $service = $this->getServiceFactory()->create($action->get('sourceEntity'));

        $offset = 0;
        $maxSize = $this->getConfig()->get('massUpdateChunkSize', 2000);

        while (true) {
            $params = [
                'disableCount' => true,
                'where'        => $data['where'],
                'select'       => ['id'],
                'offset'       => $offset,
                'maxSize'      => $maxSize,
                'sortBy'       => 'createdAt',
                'asc'          => true
            ];

            $res = $service->findEntities($params);

            if (empty($res['collection'][0])) {
                break;
            }

            foreach ($res['collection'] as $entity) {
                $input = new \stdClass();
                $input->entityId = $entity->get('id');
                $input->queueData = $data;

                try {
                    $actionType->executeNow($action, $input);
                } catch (\Throwable $e) {
                    $typeName = ucfirst($action->get('type'));
                    $GLOBALS['log']->error("Mass $typeName Action failed: " . $e->getMessage());
                }
            }

            $offset = $offset + $maxSize;
        }
    }
}
