<?php

namespace Espo\Acl;

use \Espo\ORM\Entity;

class Team extends \Espo\Core\Acl\Base
{
    public function checkInTeam(\Espo\Entities\User $user, Entity $entity)
    {
        $userTeamIdList = $user->getLinkMultipleIdList('teams');
        return in_array($entity->id, $userTeamIdList);
    }
}