<?php

namespace Espo\SelectManagers;

class EmailFolder extends \Espo\Core\SelectManagers\Base
{
    protected function access(&$result)
    {
        if (!$this->getUser()->isAdmin()) {
            $this->accessOnlyOwn($result);
        }
    }
}

