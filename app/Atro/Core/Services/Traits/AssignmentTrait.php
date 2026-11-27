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

use Espo\ORM\Entity;
use Espo\ORM\IEntity;

/**
 * The owner, the assigned users and the teams of the records: their defaults and the assignment permission of the
 * current user.
 */
trait AssignmentTrait
{
    /**
     * The assigned user and the teams of a new record that the current user cannot edit: the current user and its
     * default team.
     */
    protected function populateAssignmentDefaults(Entity $entity): void
    {
        $user               = $this->getUser()->get('delegator');
        $forbiddenFieldList = null;

        if ($entity->hasAttribute('assignedUserId')) {
            $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit');
            if (in_array('assignedUser', $forbiddenFieldList)) {
                $entity->set('assignedUserId', $user->id);
                $entity->set('assignedUserName', $user->get('name'));
            }
        }

        if ($entity->hasLinkMultipleField('teams')) {
            if (is_null($forbiddenFieldList)) {
                $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit');
            }
            if (in_array('teams', $forbiddenFieldList)) {
                if ($user->get('defaultTeamId')) {
                    $defaultTeamId = $user->get('defaultTeamId');
                    $entity->addLinkMultipleId('teams', $defaultTeamId);
                    $teamsNames = $entity->get('teamsNames');
                    if (!$teamsNames || !is_object($teamsNames)) {
                        $teamsNames = (object)[];
                    }
                    $teamsNames->$defaultTeamId = $user->get('defaultTeamName');
                    $entity->set('teamsNames', $teamsNames);
                }
            }
        }
    }

    /**
     * @param IEntity $entity
     */
    protected function setOwnerAndAssignedUser(IEntity $entity, $attachment): void
    {
        $user = $this->getUser()->get('delegator');
        // has owner param
        $hasOwner = !empty($this->getMetadata()->get(['scopes', $entity->getEntityName(), 'hasOwner']));

        if ((!property_exists($attachment, 'ownerUserId') || $entity->isNew()) && ($hasOwner || $entity->hasAttribute('ownerUserId')) && empty($entity->get('ownerUserId'))) {
            $entity->set('ownerUserId', $user->id);
        }

        // has assigned
        $hasAssigned = !empty($this->getMetadata()->get(['scopes', $entity->getEntityName(), 'hasAssignedUser']));

        if ((!property_exists($attachment, 'assignedUserId') || $entity->isNew()) && ($hasAssigned || $entity->hasAttribute('assignedUserId')) && empty($entity->get('assignedUserId'))) {
            $entity->set('assignedUserId', $user->id);
        }
    }

    public function checkAssignment(Entity $entity)
    {
        if (!empty($this->getMemoryStorage()->get('importJobId')) || $this->isPseudoTransaction()) {
            return true;
        }

        if (!$this->isPermittedAssignedUser($entity)) {
            return false;
        }
        if (!$this->isPermittedOwnerUser($entity)) {
            return false;
        }
        if (!$this->isPermittedTeams($entity)) {
            return false;
        }
        if ($entity->hasLinkMultipleField('assignedUsers')) {
            if (!$this->isPermittedAssignedUsers($entity)) {
                return false;
            }
        }
        return true;
    }

    public function isPermittedAssignedUsers(Entity $entity)
    {
        if (!$entity->hasLinkMultipleField('assignedUsers')) {
            return true;
        }

        $assignmentPermission = $this->getAcl()->get('assignmentPermission');

        if ($assignmentPermission === true || $assignmentPermission === 'yes' || !in_array($assignmentPermission, ['team', 'no'])) {
            return true;
        }

        $toProcess = false;

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('assignedUsersIds')) {
                $toProcess = true;
            }
        } else {
            $toProcess = true;
        }

        $userIdList = $entity->getLinkMultipleIdList('assignedUsers');

        if ($toProcess) {
            if (empty($userIdList)) {
                if ($assignmentPermission == 'no') {
                    return false;
                }
                return true;
            }
            $fetchedAssignedUserIdList = $entity->getFetched('assignedUsersIds');

            if ($assignmentPermission == 'no') {
                foreach ($userIdList as $userId) {
                    if (!$entity->isNew() && in_array($userId, $fetchedAssignedUserIdList)) continue;
                    if ($this->getUser()->id != $userId) {
                        return false;
                    }
                }
            } else if ($assignmentPermission == 'team') {
                $teamIdList = $this->getUser()->getLinkMultipleIdList('teams');
                foreach ($userIdList as $userId) {
                    if (!$entity->isNew() && in_array($userId, $fetchedAssignedUserIdList)) continue;
                    if (!$this->getEntityManager()->getRepository('User')->checkBelongsToAnyOfTeams($userId, $teamIdList)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    public function isPermittedOwnerUser(Entity $entity): bool
    {
        if (!$entity->hasAttribute('ownerUserId')) {
            return true;
        }

        $ownerUserId = $entity->get('ownerUserId');

        $assignmentPermission = $this->getAcl()->get('assignmentPermission');

        if ($assignmentPermission === true || $assignmentPermission === 'yes' || !in_array($assignmentPermission, ['team', 'no'])) {
            return true;
        }

        $toProcess = false;

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('ownerUserId')) {
                $toProcess = true;
            }
        } else {
            $toProcess = true;
        }

        if ($toProcess) {
            if (empty($ownerUserId)) {
                if ($assignmentPermission == 'no') {
                    return false;
                }
                return true;
            }
            if ($assignmentPermission == 'no') {
                if ($this->getUser()->id != $ownerUserId) {
                    return false;
                }
            } else if ($assignmentPermission == 'team') {
                $teamIdList = $this->getUser()->get('teamsIds');
                if (!$this->getEntityManager()->getRepository('User')->checkBelongsToAnyOfTeams($ownerUserId, $teamIdList)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function isPermittedAssignedUser(Entity $entity)
    {
        if (!$entity->hasAttribute('assignedUserId')) {
            return true;
        }

        $assignedUserId = $entity->get('assignedUserId');

        $assignmentPermission = $this->getAcl()->get('assignmentPermission');

        if ($assignmentPermission === true || $assignmentPermission === 'yes' || !in_array($assignmentPermission, ['team', 'no'])) {
            return true;
        }

        $toProcess = false;

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('assignedUserId')) {
                $toProcess = true;
            }
        } else {
            $toProcess = true;
        }

        if ($toProcess) {
            if (empty($assignedUserId)) {
                if ($assignmentPermission == 'no') {
                    return false;
                }
                return true;
            }
            if ($assignmentPermission == 'no') {
                if ($this->getUser()->id != $assignedUserId) {
                    return false;
                }
            } else if ($assignmentPermission == 'team') {
                $teamIdList = $this->getUser()->get('teamsIds');
                if (!$this->getEntityManager()->getRepository('User')->checkBelongsToAnyOfTeams($assignedUserId, $teamIdList)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function isPermittedTeams(Entity $entity)
    {
        $assignmentPermission = $this->getAcl()->get('assignmentPermission');

        if (empty($assignmentPermission) || $assignmentPermission === true || !in_array($assignmentPermission, ['team', 'no'])) {
            return true;
        }

        if (!$entity->hasLinkMultipleField('teams')) {
            return true;
        }
        $teamIdList = $entity->getLinkMultipleIdList('teams');
        if (empty($teamIdList)) {
            if ($assignmentPermission === 'team') {
                if ($entity->hasLinkMultipleField('assignedUsers')) {
                    $assignedUserIdList = $entity->getLinkMultipleIdList('assignedUsers');
                    if (empty($assignedUserIdList)) {
                        return false;
                    }
                } else if ($entity->hasAttribute('assignedUserId')) {
                    if (!$entity->get('assignedUserId')) {
                        return false;
                    }
                }
            }
            return true;
        }

        $newIdList = [];

        if (!$entity->isNew()) {
            $existingIdList = $this->getStoredTeamIds($entity);
            foreach ($teamIdList as $id) {
                if (!in_array($id, $existingIdList)) {
                    $newIdList[] = $id;
                }
            }
        } else {
            $newIdList = $teamIdList;
        }

        if (empty($newIdList)) {
            return true;
        }

        $userTeamIdList = $this->getUser()->getLinkMultipleIdList('teams');

        foreach ($newIdList as $id) {
            if (!in_array($id, $userTeamIdList)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The ids of the teams the record has in the database.
     */
    protected function getStoredTeamIds(Entity $entity): array
    {
        $ids = [];
        foreach ($entity->get('teams') ?? [] as $team) {
            $ids[] = $team->id;
        }

        return $ids;
    }
}
