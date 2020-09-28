<?php

namespace Espo\SelectManagers;

class ActionHistoryRecord extends \Espo\Core\SelectManagers\Base
{
    protected function boolFilterOnlyMy(&$result)
    {
        $this->accessOnlyOwn($result);
    }

    protected function accessOnlyOwn(&$result)
    {
        $result['whereClause'][] = array(
            'userId' => $this->getUser()->id
        );
    }
}
