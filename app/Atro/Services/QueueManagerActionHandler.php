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

use Atro\ActionTypes\TypeInterface;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Metadata;
use Atro\Services\QueueManagerBase;
use Espo\Services\Record;

class QueueManagerActionHandler extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        $action = $this->getEntityManager()->getRepository('Action')->get($data['actionId']);

        if (empty($action->get('sourceEntity'))) {
            return true;
        }
        if (!empty($data['sourceEntity'])) {
            $action->set('sourceEntity', $data['sourceEntity']);
        }

        /** @var Metadata $metadata */
        $metadata = $this->getContainer()->get('metadata');

        /** @var TypeInterface $actionType */
        $actionType = $this->getContainer()->get($metadata->get(['action', 'types', $action->get('type')]));

        /** @var ServiceFactory $sf */
        $sf = $this->getContainer()->get('serviceFactory');

        /** @var Record $service */
        $service = $sf->create($action->get('sourceEntity'));

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

        return true;
    }
}
