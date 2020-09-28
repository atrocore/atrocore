<?php

namespace Espo\SelectManagers;

class Import extends \Espo\Core\SelectManagers\Base
{

    protected function access(&$result)
    {
        if (!$this->getUser()->isAdmin()) {
            $result['whereClause'][] = [
                'createdById' => $this->getUser()->id
            ];
        }
    }
}
