<?php

namespace Espo\SelectManagers;

class AuthLogRecord extends \Espo\Core\SelectManagers\Base
{
    protected function filterDenied(&$result)
    {
        $result['whereClause'][] = array(
            'isDenied' => true
        );
    }

    protected function filterAccepted(&$result)
    {
        $result['whereClause'][] = array(
            'isDenied' => false
        );
    }
}

