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

namespace Atro\Core\Services\Traits;

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotModified;

/**
 * Updating, deleting and restoring the records in bulk.
 */
trait MassActionsTrait
{
    public function massUpdate($data, array $params)
    {
        $event = $this->dispatchEvent('beforeMassUpdate', new Event(['data' => $data, 'service' => $this, 'params' => $params]));

        $data   = $event->getArgument('data');
        $params = $event->getArgument('params');

        $data->__massUpdate = true;

        $this->filterInput($data);

        $params['action']             = 'update';
        $params['maxCountWithoutJob'] = $params['maxCountWithoutJob'] ?? $this->getConfig()->get('massUpdateMaxCountWithoutJob', 200);
        $params['maxChunkSize']       = $this->getConfig()->get('massUpdateMaxChunkSize', 3000);
        $params['minChunkSize']       = $this->getConfig()->get('massUpdateMinChunkSize', 400);
        $params['additionalJobData']  = ['input' => clone $data];

        $input = clone $data;
        [$count, $errors, $sync, $jobEntity] = array_pad(
            $this->executeMassAction($params, function ($id) use ($input) {
                try {
                    $this->updateEntity($id, $input);
                } catch (NotModified $e) {
                }
            }),
            4, null
        );

        $result = $sync
            ? ['count' => $count, 'errors' => $errors]
            : ['jobId' => $jobEntity->get('id')];

        return $this
            ->dispatchEvent('afterMassUpdate', new Event(['data' => $data, 'service' => $this, 'result' => $result]))
            ->getArgument('result');
    }

    public function massRestore(array $params)
    {
        $params = $this
            ->dispatchEvent('beforeMassRestore', new Event(['params' => $params, 'service' => $this]))
            ->getArgument('params');

        if (!$this->getAcl()->check($this->entityName, 'edit')) {
            throw new Forbidden();
        }

        $params['action']             = 'restore';
        $params['maxCountWithoutJob'] = $params['maxCountWithoutJob'] ?? $this->getConfig()->get('massRestoreMaxCountWithoutJob', 200);
        $params['maxChunkSize']       = $this->getConfig()->get('massRestoreMaxChunkSize', 3000);
        $params['minChunkSize']       = $this->getConfig()->get('massRestoreMinChunkSize', 400);
        $params['singleActionMethod'] = 'restoreEntity';

        [$count, $errors, $sync, $jobEntity] = array_pad(
            $this->executeMassAction($params, function ($id) {
                $this->restoreEntity($id);
            }),
            4, null
        );

        $result = $sync
            ? ['count' => $count, 'errors' => $errors]
            : ['jobId' => $jobEntity->get('id')];

        return $this
            ->dispatchEvent('afterMassRestore', new Event(['service' => $this, 'result' => $result]))
            ->getArgument('result');
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function massRemove(array $params)
    {
        $params = $this
            ->dispatchEvent('beforeMassDelete', new Event(['params' => $params, 'service' => $this]))
            ->getArgument('params');

        $params['action']             = 'delete';
        $params['maxCountWithoutJob'] = $params['maxCountWithoutJob'] ?? $this->getConfig()->get('massDeleteMaxCountWithoutJob', 200);
        $params['maxChunkSize']       = $this->getConfig()->get('massDeleteMaxChunkSize', 3000);
        $params['minChunkSize']       = $this->getConfig()->get('massDeleteMinChunkSize', 400);
        $params['singleActionMethod'] = !empty($params['permanently']) ? 'deleteEntityPermanently' : 'deleteEntity';

        if (!empty($params['permanently'])) {
            $callback = function ($id) {
                $this->deleteEntityPermanently($id);
            };
        } else {
            $callback = function ($id) {
                $this->deleteEntity($id);
            };
        }

        [$count, $errors, $sync, $jobEntity] = array_pad(
            $this->executeMassAction($params, $callback),
            4, null
        );

        $result = $sync
            ? ['count' => $count, 'errors' => $errors]
            : ['jobId' => $jobEntity->get('id')];

        return $this
            ->dispatchEvent('afterMassDelete', new Event(['service' => $this, 'result' => $result]))
            ->getArgument('result');
    }
}
