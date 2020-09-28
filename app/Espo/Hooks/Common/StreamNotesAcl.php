<?php

namespace Espo\Hooks\Common;

use Espo\ORM\Entity;

class StreamNotesAcl extends \Espo\Core\Hooks\Base
{
    protected $streamService = null;

    public static $order = 10;

    protected function init()
    {
        parent::init();
        $this->addDependency('serviceFactory');
        $this->addDependency('aclManager');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getAclManager()
    {
        return $this->getInjection('aclManager');
    }

    public function afterSave(Entity $entity, array $options = [])
    {
        if (!empty($options['noStream'])) return;
        if (!empty($options['silent'])) return;

        if (!empty($options['skipStreamNotesAcl'])) return;

        if ($entity->isNew()) return;

        $entityType = $entity->getEntityType();

        if (in_array($entityType, ['Note', 'User', 'Team', 'Role', 'Portal', 'PortalRole'])) return;

        if (!$this->getMetadata()->get(['scopes', $entityType, 'acl'])) return;
        if (!$this->getMetadata()->get(['scopes', $entityType, 'object'])) return;

        $ownerUserIdAttribute = $this->getAclManager()->getImplementation($entityType)->getOwnerUserIdAttribute($entity);

        $usersAttributeIsChanged = false;
        $teamsAttributeIsChanged = false;

        if ($ownerUserIdAttribute) {
            if ($entity->isAttributeChanged($ownerUserIdAttribute)) {
                $usersAttributeIsChanged = true;
                if ($entity->getAttributeParam($ownerUserIdAttribute, 'isLinkMultipleIdList')) {
                    $userIdList = $entity->get($ownerUserIdAttribute);
                } else {
                    $userId = $entity->get($ownerUserIdAttribute);
                    if ($userId) {
                        $userIdList = [$userId];
                    } else {
                        $userIdList = [];
                    }
                }
            }
        }

        if ($entity->hasLinkMultipleField('teams') && $entity->isAttributeChanged('teamsIds')) {
            $teamsAttributeIsChanged = true;
            $teamIdList = $entity->get('teamsIds');
        }

        if ($usersAttributeIsChanged || $teamsAttributeIsChanged) {
            $noteList = $this->getEntityManager()->getRepository('Note')->where([
                'OR' => [
                    [
                        'relatedId' => $entity->id,
                        'relatedType' => $entityType
                    ],
                    [
                        'parentId' => $entity->id,
                        'parentType' => $entityType,
                        'superParentId!=' => null,
                        'relatedId' => null
                    ]
                ]
            ])->select(['id'])->find();

            foreach ($noteList as $note) {
                if ($teamsAttributeIsChanged) {
                    $note->set('teamsIds', $teamIdList);
                }
                if ($usersAttributeIsChanged) {
                    $note->set('usersIds', $userIdList);
                }
                $this->getEntityManager()->saveEntity($note);
            }
        }
    }
}
