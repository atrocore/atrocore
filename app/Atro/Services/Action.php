<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Services;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Templates\Services\Base;
use Atro\ActionTypes\TypeInterface;

class Action extends Base
{
    protected $mandatorySelectAttributeList = ['data'];

    public function executeNow(string $id, \stdClass $input): bool
    {
        $action = $this->getRepository()->get($id);
        if (empty($action)) {
            throw new NotFound();
        }

        return $this->getActionType($action->get('type'))->executeNow($action, $input);
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
