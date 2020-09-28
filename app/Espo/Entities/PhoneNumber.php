<?php

namespace Espo\Entities;

use Espo\Core\Exceptions\Error;

class PhoneNumber extends \Espo\Core\ORM\Entity
{
    protected function _setName($value)
    {
        if (empty($value)) {
            throw new Error("Phone number can't be empty");
        }
        $this->valuesContainer['name'] = $value;
    }
}

