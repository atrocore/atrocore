<?php

namespace Espo\Entities;

class User extends \Espo\Core\Entities\Person
{
    public function isAdmin()
    {
        return $this->get('isAdmin');
    }

    public function isSystem()
    {
        return $this->id === 'system';
    }

    public function isActive()
    {
        return $this->get('isActive');
    }

    public function isPortal()
    {
        return $this->isPortalUser();
    }

    public function isPortalUser()
    {
        return $this->get('isPortalUser');
    }

    public function getTeamIdList()
    {
        if (!$this->has('teamsIds')) {
            $this->loadLinkMultipleField('teams');
        }
        return $this->get('teamsIds');
    }

    public function loadAccountField()
    {
        if ($this->get('contactId')) {
            $contact = $this->getEntityManager()->getEntity('Contact', $this->get('contactId'));
            if ($contact && $contact->get('accountId')) {
                $this->set('accountId', $contact->get('accountId'));
                $this->set('accountName', $contact->get('accountName'));
            }
        }
    }
}
