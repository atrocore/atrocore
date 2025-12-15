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

use Atro\Console\CreateAction;
use Atro\Console\CreateConditionType;
use Atro\Core\ActionManager;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Utils\Language;
use Atro\Repositories\SavedSearch as SavedSearchRepo;
use Doctrine\DBAL\ParameterType;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Atro\ActionTypes\TypeInterface;
use Espo\ORM\Entity;

class Action extends Base
{
    protected $mandatorySelectAttributeList = ['searchEntity', 'targetEntity', 'data'];

    protected function handleInput(\stdClass $data, ?string $id = null): void
    {
        if (property_exists($data, 'conditions') && !is_string($data->conditions)) {
            $data->conditions = @json_encode($data->conditions);
        }

        parent::handleInput($data, $id);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        if ($entity->get('conditionsType') === 'basic') {
            $entity->set('conditions', @json_decode($entity->get('conditions')));
        }

        parent::prepareEntityForOutput($entity);

        $fileName = CreateAction::DIR . '/' . str_replace('custom', '', $entity->get('type') ?? '') . '.php';
        $entity->set('typePhpCode', null);
        if (file_exists($fileName)) {
            $entity->set('typePhpCode', file_get_contents($fileName));
        }

        $fileName = CreateConditionType::DIR . '/' . $entity->get('conditionsType') . '.php';
        $entity->set('conditionPhpCode', null);
        if (file_exists($fileName)) {
            $entity->set('conditionPhpCode', file_get_contents($fileName));
        }
    }

    public function executeRecordAction(string $id, string $entityId, string $actionName, $payload = null): array
    {
        $action = $this->getRepository()->where(['id' => $id])->findOne();
        if (empty($action)) {
            throw new NotFound();
        }

        $actionType = $this->getActionType($action->get('type'));

        $method = "execute" . ucfirst($actionName);
        if (!method_exists($actionType, $method)) {
            throw new NotFound();
        }

        return $actionType->$method($action, $entityId, $payload);
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

        if (!empty($action->get('sourceEntity'))) {
            $dynamicRecordAction = null;
            foreach ($this->getMetadata()->get("clientDefs.{$action->get('sourceEntity')}.dynamicRecordActions") ?? [] as $dra) {
                if ($dra['id'] == $action->get('id')) {
                    $dynamicRecordAction = $dra;
                    break;
                }
            }
            if (!empty($dynamicRecordAction['acl'])) {
                if (!$this->getAcl()->check($dynamicRecordAction['acl']['scope'],
                    $dynamicRecordAction['acl']['action'])) {
                    throw new Forbidden();
                }
            }
        }

        $success = $this->getActionManager()->executeNow($action, $input);
        if ($success) {
            $message = sprintf($this->getLanguage()->translate('actionExecuted', 'messages'), $action->get('name'));
        }

        $result = [
            'inBackground' => $action->get('inBackground'),
            'success'      => $success,
            'message'      => $message ?? null,
        ];

        return $this
            ->dispatchEvent('afterExecuteNow', new Event(['result' => $result, 'action' => $action, 'input' => $input]))
            ->getArgument('result');
    }

    public function updateEntity($id, $data)
    {
        if (
            property_exists($data, '_link')
            && $data->_link === 'actions'
            && !empty($data->_id)
            && !empty($data->_sortedIds)
        ) {
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
        $this->addDependency('actionManager');
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('container')->get('language');
    }

    protected function getActionType(string $type): TypeInterface
    {
        $className = $this->getMetadata()->get(['action', 'types', $type]);
        if (empty($className)) {
            throw new Error("No such action type '$type'.");
        }
        return $this->getInjection('container')->get($className);
    }

    public function getDynamicActions(string $scope, ?string $id, ?string $type, ?string $display)
    {
        if ($this->getMetadata()->get(['scopes', $scope, 'actionDisabled'], false)) {
            throw new Error("Action for '$scope' disabled");
        }

        $entity = $id !== null ? $this->getServiceFactory()->create($scope)->getEntity($id) : null;

        $res = [];

        $dynamicActions = [];
        $dynamicPreviewActions = [];
        $actionIds = [];
        $key = $type === 'field' ? 'dynamicFieldActions' : 'dynamicRecordActions';

        foreach ($this->getMetadata()->get(['clientDefs', $scope, $key]) ?? [] as $action) {
            if (!empty($action['acl']['scope'])) {
                if (!$this->getAcl()->check($action['acl']['scope'], $action['acl']['action'])) {
                    continue;
                }
            }
            if (!empty($display) && (empty($action['display']) || $display !== $action['display'])) {
                continue;
            }

            $data = [
                'action'  => 'dynamicAction',
                'label'   => $action['name'],
                'display' => $action['display'] ?? null,
                'type'    => $action['type'] ?? null,
                'html' => $action['html'] ?? null,
                'tooltip' => $action['tooltip'] ?? null,
                'data'    => [
                    'action_id' => $action['id'],
                    'entity_id' => $id
                ]
            ];

            if ($type === 'field') {
                $data['displayField'] = $action['displayField'] ?? null;
            }


            if ($data['type'] === 'previewTemplate') {
                if (!empty($action['data']['where']) && !empty($action['data']['whereScope']) && $action['data']['whereScope'] === $scope) {
                    $where = $action['data']['where'];
                    if (!$this->getServiceFactory()->create('PreviewTemplate')->canExecute($scope, $id, $where)) {
                        continue;
                    }
                }
                $dynamicPreviewActions[] = $data;
                continue;
            }

            $actionIds[] = $action['id'];
            $dynamicActions[] = $data;
        }

        if (!empty($actionIds)) {
            $actions = $this->getEntityManager()->getRepository('Action')->findByIds($actionIds);

            foreach ($actions as $action) {
                foreach ($dynamicActions as $dynamicAction) {
                    if ($action->get('id') === $dynamicAction['data']['action_id']) {
                        $input = new \stdClass();
                        $input->sourceEntity = $entity;

                        try {
                            if ($this->getActionManager()->canExecute($action, $input)) {
                                $res[] = $dynamicAction;
                            }
                        } catch (\Throwable $e) {
                            $GLOBALS['log']->error("Condition check failed for action {$action->get('id')} and record $id :" . $e->getMessage());
                        }

                        break;
                    }
                }
            }
        }


        if (!$this->getMetadata()->get(['scopes', $scope, 'bookmarkDisabled']) && $type === 'record') {
            $result = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('bookmark')
                ->where('entity_id = :entityId AND deleted = :false')
                ->andWhere('entity_type = :entityType')
                ->andWhere('user_id = :userId')
                ->setParameter('entityId', $id)
                ->setParameter('entityType', $scope)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('userId', $this->getUser()->id)
                ->fetchAssociative();

            $res[] = [
                'action' => 'bookmark',
                'label'  => empty($result['id']) ? 'Bookmark' : 'Unbookmark',
                'data'   => [
                    'entity_id'   => $id,
                    'bookmark_id' => $result['id'] ?? null
                ]
            ];
        }

        return array_merge($dynamicPreviewActions, $res);
    }

    protected function getActionManager(): ActionManager
    {
        return $this->getInjection('actionManager');
    }
}
