<?php

namespace Espo\Core\Notificators;

use \Espo\Core\Interfaces\Injectable;

use \Espo\ORM\Entity;

class Base implements Injectable
{
    protected $dependencies = array(
        'user',
        'entityManager',
    );

    protected $injections = array();

    public static $order = 9;

    public function __construct()
    {
        $this->init();
    }

    protected function init()
    {
    }

    protected function addDependencyList(array $list)
    {
        foreach ($list as $item) {
            $this->addDependency($item);
        }
    }

    protected function addDependency($name)
    {
        $this->dependencies[] = $name;
    }

    public function getDependencyList()
    {
        return $this->dependencies;
    }

    protected function getInjection($name)
    {
        return $this->injections[$name];
    }

    public function inject($name, $object)
    {
        $this->injections[$name] = $object;
    }

    protected function getEntityManager()
    {
        return $this->injections['entityManager'];
    }

    protected function getUser()
    {
        return $this->injections['user'];
    }

    public function process(Entity $entity)
    {
        if ($entity->hasLinkMultipleField('assignedUsers')) {
            $userIdList = $entity->getLinkMultipleIdList('assignedUsers');
            $fetchedAssignedUserIdList = $entity->getFetched('assignedUsersIds');
            if (!is_array($fetchedAssignedUserIdList)) {
                $fetchedAssignedUserIdList = [];
            }

            foreach ($userIdList as $userId) {
                if (in_array($userId, $fetchedAssignedUserIdList)) continue;
                $this->processForUser($entity, $userId);
            }
        } else {
            if (!$entity->get('assignedUserId')) return;
            if (!$entity->isAttributeChanged('assignedUserId')) return;
            $assignedUserId = $entity->get('assignedUserId');
            $this->processForUser($entity, $assignedUserId);
        }
    }

    protected function processForUser(Entity $entity, $assignedUserId)
    {
        if ($entity->hasAttribute('createdById') && $entity->hasAttribute('modifiedById')) {
            if ($entity->isNew()) {
                $isNotSelfAssignment = $assignedUserId !== $entity->get('createdById');
            } else {
                $isNotSelfAssignment = $assignedUserId !== $entity->get('modifiedById');
            }
        } else {
            $isNotSelfAssignment = $assignedUserId !== $this->getUser()->id;
        }
        if (!$isNotSelfAssignment) return;

        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set(array(
            'type' => 'Assign',
            'userId' => $assignedUserId,
            'data' => array(
                'entityType' => $entity->getEntityType(),
                'entityId' => $entity->id,
                'entityName' => $entity->get('name'),
                'isNew' => $entity->isNew(),
                'userId' => $this->getUser()->id,
                'userName' => $this->getUser()->get('name')
            )
        ));
        $this->getEntityManager()->saveEntity($notification);
    }
}
