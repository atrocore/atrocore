<?php

namespace Espo\Entities;

use Espo\Core\Exceptions\Error;

class EmailAddress extends \Espo\Core\ORM\Entity
{

    protected function _setName($value)
    {
        if (empty($value)) {
            throw new Error("Not valid email address '{$value}'");
        }
        $this->valuesContainer['name'] = $value;
        $this->set('lower', strtolower($value));
    }
}
