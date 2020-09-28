<?php

namespace Espo\SelectManagers;

class ScheduledJob extends \Espo\Core\SelectManagers\Base
{
    protected function access(&$result)
    {
        parent::access($result);

        $result['whereClause'][] = array(
            'isInternal' => false
        );
    }
}
