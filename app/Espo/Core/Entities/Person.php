<?php

namespace Espo\Core\Entities;

class Person extends \Espo\Core\ORM\Entity
{
    public function _setLastName($value)
    {
        $this->setValue('lastName', $value);

        $firstName = $this->get('firstName');
        if (empty($firstName)) {
            $this->setValue('name', $value);
        } else {
            $this->setValue('name', $firstName . ' ' . $value);
        }
    }

    public function _setFirstName($value)
    {
        $this->setValue('firstName', $value);

        $lastName = $this->get('lastName');
        if (empty($lastName)) {
            $this->setValue('name', $value);
        } else {
            $this->setValue('name', $value . ' ' . $lastName);
        }
    }
}

