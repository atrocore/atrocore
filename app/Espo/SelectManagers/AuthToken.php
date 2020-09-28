<?php

namespace Espo\SelectManagers;

class AuthToken extends \Espo\Core\SelectManagers\Base
{
    protected function filterActive(&$result)
    {
        $result['whereClause'][] = array(
            'isActive' => true
        );
    }

    protected function filterInactive(&$result)
    {
        $result['whereClause'][] = array(
            'isActive' => false
        );
    }
}

