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

use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Atro\ActionTypes\TypeInterface;

class Action extends Base
{
    protected $mandatorySelectAttributeList = ['data'];

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
