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

use Espo\Core\EventManager\Event;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Atro\ActionTypes\TypeInterface;

class Action extends Base
{
    protected $mandatorySelectAttributeList = ['targetEntity', 'data'];

    public function getEmailPreview(string $id, string $entityId): array
    {
        $action = $this->getRepository()->where(['id' => $id, 'type' => 'email'])->findOne();
        if (empty($action)) {
            throw new NotFound();
        }

        return $this->getActionType($action->get('type'))->getEmailPreview($action, $entityId);
    }

    public function executeNow(string $id, \stdClass $input): array
    {
        $event = $this->dispatchEvent('beforeExecuteNow', new Event(['id' => $id, 'input' => $input]));

        $id = $event->getArgument('id');
        $input = $event->getArgument('input');

        $action = $this->getRepository()->get($id);
        if (empty($action)) {
            throw new NotFound();
        }

        $success = $this->getActionType($action->get('type'))->executeNow($action, $input);
        if ($success) {
            $message = sprintf($this->getInjection('container')->get('language')->translate('actionExecuted', 'messages'), $action->get('name'));
        } else {
            $message = 'Something wrong';
        }

        $result = [
            'inBackground' => $action->get('inBackground'),
            'success'      => $success,
            'message'      => $message,
        ];

        return $this
            ->dispatchEvent('afterExecuteNow', new Event(['result' => $result, 'action' => $action, 'input' => $input]))
            ->getArgument('result');
    }

    public function updateEntity($id, $data)
    {
        if (property_exists($data, '_link') && $data->_link === 'actions' && !empty($data->_id) && !empty($data->_sortedIds)) {
            $collection = $this->getEntityManager()->getRepository('ActionSetLinker')
                ->where([
                    'setId'    => $data->_id,
                    'actionId' => $data->_sortedIds
                ])
                ->find();
            if (!empty($collection[0])) {
                $service = $this->getServiceFactory()->create('ActionSetLinker');
                foreach ($data->_sortedIds as $k => $id) {
                    foreach ($collection as $entity) {
                        if ($entity->get('actionId') === $id) {
                            $input = new \stdClass();
                            $input->sortOrder = $k;
                            try {
                                $service->updateEntity($entity->get('id'), $input);
                            } catch (\Throwable $e) {
                            }
                            continue 2;
                        }
                    }
                }
            }

            return $this->getEntity($id);
        }

        return parent::updateEntity($id, $data);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getActionType(string $type): TypeInterface
    {
        $className = $this->getMetadata()->get(['action', 'types', $type]);
        if (empty($className)) {
            throw new Error("No such action type '$type'.");
        }
        return $this->getInjection('container')->get($className);
    }
}
