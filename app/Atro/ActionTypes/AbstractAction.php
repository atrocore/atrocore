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

use Atro\ConditionTypes\AbstractConditionType;
use Atro\Core\ActionManager;
use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\KeyValueStorages\MemoryStorage;
use Atro\Core\Twig\Twig;
use Atro\Entities\ActionExecution;
use Atro\Repositories\SavedSearch;
use Espo\Core\ORM\EntityManager;
use Espo\Core\ServiceFactory;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Espo\ORM\Entity;

abstract class AbstractAction implements TypeInterface
{
    protected Container $container;

    public static function getTypeLabel(): ?string
    {
        return null;
    }

    public static function getName(): ?string
    {
        return null;
    }

    public static function getDescription(): ?string
    {
        return null;
    }

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function useMassActions(Entity $action, \stdClass $input): bool
    {
        return true;
    }

    public function canExecute(Entity $action, \stdClass $input): bool
    {
        if ($action->get('conditionsType') === 'basic') {
            if (!empty($input->uiRecord)) {
                $sourceEntity = $this->getEntityManager()->getRepository($action->get('sourceEntity'))->get();
                $sourceEntity->set($input->uiRecord);
            } else {
                $sourceEntity = $this->getSourceEntity($action, $input);
            }
            if (empty($sourceEntity)) {
                return true;
            }
            $conditions = @json_decode($action->get('conditions'), true);
            if (!empty($conditions)) {
                if ($sourceEntity->getEntityType() !== $action->get('sourceEntity')) {
                    return false;
                }
                return $this->container->get('condition')->check($sourceEntity, $conditions);
            }
            return true;
        } elseif ($action->get('conditionsType') === 'script') {
            $template = empty($action->get('conditions')) ? '' : (string)$action->get('conditions');
            $templateData = [
                'entity'          => $this->getSourceEntity($action, $input),
                'triggeredEntity' => $input->triggeredEntity ?? null,
                'user'            => $this->getEntityManager()->getUser(),
                'importJobId'     => $this->container->get('memoryStorage')->get('importJobId')
            ];

            foreach (['uiRecord', 'uiRecordFrom', 'uiRecordFromName'] as $key) {
                if (!empty($input->$key)) {
                    $templateData[$key] = $input->$key;
                }
            }

            if (
                empty($templateData['triggeredEntity'])
                && property_exists($input, 'triggeredEntityType')
                && property_exists($input, 'triggeredEntityId')
            ) {
                $templateData['triggeredEntity'] = $this
                    ->getEntityManager()
                    ->getRepository($input->triggeredEntityType)
                    ->get($input->triggeredEntityId);
            }

            $res = $this->getTwig()->renderTemplate($template, $templateData, 'bool');
            if (is_string($res)) {
                throw new BadRequest('Action conditions error: ' . $res);
            }

            return $res;
        }

        $className = $this->getMetadata()->get("app.conditionsTypes.{$action->get('conditionsType')}.className");
        if ($className && is_a($className, AbstractConditionType::class, true)) {
            $input->actionEntity = $action;
            return $this->container->get($className)->proceed($input);
        }

        return true;
    }

    public function execute(ActionExecution $execution, \stdClass $input): bool
    {
        // for backward compatibility
        if (method_exists($this, 'executeNow')) {
            $action = $execution->get('action');
            try {
                $res = $this->executeNow($action, $input);
                $execution->set('status', 'done');
            } catch (\Throwable $e) {
                $res = false;
                $execution->set('status', 'failed');
                $execution->set('statusMessage', $e->getMessage());

                if ($e instanceof BadRequest && $action->get('type') === 'error') {
                    $res = true;
                    $execution->set('status', 'done');
                }
            }
            $this->getEntityManager()->saveEntity($execution);

            if (!empty($e) && empty($res)) {
                throw $e;
            }

            return $res;
        }

        return true;
    }

    public function getSourceEntity($action, \stdClass $input): ?Entity
    {
        $sourceEntity = null;
        if (!empty($input->sourceEntity)) {
            $sourceEntity = $input->sourceEntity;
        } elseif (!empty($action->get('sourceEntity')) && property_exists($input, 'entityId')) {
            $sourceEntity = $this->getEntityManager()->getRepository($action->get('sourceEntity'))->get($input->entityId);
        } elseif (!empty($input->triggeredEntity)) {
            $sourceEntity = $input->triggeredEntity;
        } elseif (property_exists($input, 'triggeredEntityType') && property_exists($input, 'triggeredEntityId')) {
            $sourceEntity = $this->getEntityManager()->getRepository($input->triggeredEntityType)->get($input->triggeredEntityId);
        }

        return $sourceEntity;
    }

    protected function getWhere(Entity $action): ?array
    {
        return !empty($action->get('data')->where) ? $action->get('data')->where : null;
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
}