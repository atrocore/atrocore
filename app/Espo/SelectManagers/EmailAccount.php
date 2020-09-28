<?php

namespace Espo\SelectManagers;

class EmailAccount extends \Espo\Core\SelectManagers\Base
{
    public function access(&$result)
    {
        if (!$this->user->isAdmin()) {
        	$result['whereClause'][] = array(
        		'assignedUserId' => $this->user->id
        	);
        }
    }
}

