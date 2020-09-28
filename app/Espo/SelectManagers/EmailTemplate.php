<?php

namespace Espo\SelectManagers;

class EmailTemplate extends \Espo\Core\SelectManagers\Base
{
    protected function filterActual(&$result)
    {

        $result['whereClause'][] = array(
            'oneOff!=' => true
        );
    }

}
