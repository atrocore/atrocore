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

namespace Atro\ActionTypes;

use Atro\Core\ActionManager;
use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\KeyValueStorages\MemoryStorage;
use Atro\Core\Twig\Twig;
use Atro\Core\Utils\Condition\Condition;
use Espo\Core\ORM\EntityManager;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;

abstract class AbstractAction implements TypeInterface
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function canExecute(Entity $action, \stdClass $input): bool
    {
        $sourceEntity = $this->getSourceEntity($action, $input);
        if ($action->get('conditionsType') === 'basic') {
            if (empty($sourceEntity)) {
                return true;
            }
            $conditions = @json_decode($action->get('conditions'), true);
            if (!empty($conditions)) {
                if ($sourceEntity->getEntityType() !== $action->get('sourceEntity')) {
                    return false;
                }
                return Condition::isCheck(Condition::prepare($sourceEntity, $conditions));
            }
            return true;
        }

        if ($action->get('conditionsType') === 'script') {
            $template = empty($action->get('conditions')) ? '' : (string)$action->get('conditions');
            $templateData = [
                'entity' => $sourceEntity,
                'user'   => $this->getEntityManager()->getUser()
            ];

            $res = $this->getTwig()->renderTemplate($template, $templateData, 'bool');
            if (is_string($res)) {
                throw new BadRequest('Action conditions error: ' . $res);
            }

            return $res;
        }

        return true;
    }

    public function createJob(Entity $action, \stdClass $input): bool
    {
        if (!property_exists($input, 'where')) {
            return false;
        }

        $data = ['actionId'     => $action->get('id'),
                 'sourceEntity' => $action->get('sourceEntity'),
                 'where'        => $input->where
        ];
        if (property_exists($input, 'actionSetLinkerId')) {
            $data['actionSetLinkerId'] = $input->actionSetLinkerId;
        }

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'    => $action->get('name'),
            'type'    => 'ActionHandler',
            'payload' => $data
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);

        return true;
    }

    public function getSourceEntity($action, \stdClass $input): ?Entity
    {
        $sourceEntity = null;
        if (!empty($input->sourceEntity)) {
            $sourceEntity = $input->sourceEntity;
        } else if (!empty($action->get('sourceEntity')) && property_exists($input, 'entityId')) {
            $sourceEntity = $this->getEntityManager()->getRepository($action->get('sourceEntity'))->get($input->entityId);
        } elseif (!empty($input->triggeredEntity)) {
            $sourceEntity = $input->triggeredEntity;
        } elseif (property_exists($input, 'triggeredEntityType') && property_exists($input, 'triggeredEntityId')) {
            $sourceEntity = $this->getEntityManager()->getRepository($input->triggeredEntityType)->get($input->triggeredEntityId);
        }

        return $sourceEntity;
    }

    public function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getTwig(): Twig
    {
        return $this->container->get('twig');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getMemoryStorage(): MemoryStorage
    {
        return $this->container->get('memoryStorage');
    }

    protected function getActionManager(): ActionManager
    {
        return $this->container->get('actionManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    public function getActionById(string $id): Entity
    {
        return $this->getEntityManager()->getEntity('Action', $id);
    }
}