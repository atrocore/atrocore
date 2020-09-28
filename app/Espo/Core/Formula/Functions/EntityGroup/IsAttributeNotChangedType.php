<?php

namespace Espo\Core\Formula\Functions\EntityGroup;

use \Espo\ORM\Entity;

class IsAttributeNotChangedType extends IsAttributeChangedType
{
    protected function check($attribute)
    {
        return !parent::check($attribute);
    }
}