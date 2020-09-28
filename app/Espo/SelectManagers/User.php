<?php

namespace Espo\SelectManagers;

class User extends \Espo\Core\SelectManagers\Base
{
    protected function access(&$result)
    {
        parent::access($result);

        if (!$this->getUser()->isAdmin()) {
            $result['whereClause'][] = array(
                'isActive' => true
            );
        }
        if ($this->getAcl()->get('portalPermission') !== 'yes') {
            $result['whereClause'][] = array(
                'OR' => [
                    ['isPortalUser' => false],
                    ['id' => $this->getUser()->id]
                ]
            );
        }
        $result['whereClause'][] = array(
            'isSuperAdmin' => false
        );
    }

    protected function filterActive(&$result)
    {
        $result['whereClause'][] = array(
            'isActive' => true,
            'isPortalUser' => false
        );
    }

    protected function filterActivePortal(&$result)
    {
        $result['whereClause'][] = array(
            'isActive' => true,
            'isPortalUser' => true
        );
    }

    protected function filterPortal(&$result)
    {
        $result['whereClause'][] = array(
            'isPortalUser' => true
        );
    }

    protected function filterInternal(&$result)
    {
        $result['whereClause'][] = array(
            'isPortalUser' => false
        );
    }

    protected function boolFilterOnlyMyTeam(&$result)
    {
        $this->addJoin('teams', $result);
        $result['whereClause'][] = array(
        	'teamsMiddle.teamId' => $this->getUser()->getLinkMultipleIdList('teams')
        );
        $this->setDistinct(true, $result);
    }

    protected function accessOnlyOwn(&$result)
    {
        $result['whereClause'][] = array(
            'id' => $this->getUser()->id
        );
    }

    protected function accessPortalOnlyOwn(&$result)
    {
        $result['whereClause'][] = array(
            'id' => $this->getUser()->id
        );
    }

    protected function accessOnlyTeam(&$result)
    {
        $this->setDistinct(true, $result);
        $this->addLeftJoin(['teams', 'teamsAccess'], $result);
        $result['whereClause'][] = array(
            'OR' => array(
                'teamsAccess.id' => $this->getUser()->getLinkMultipleIdList('teams'),
                'id' => $this->getUser()->id
            )
        );
    }
}

