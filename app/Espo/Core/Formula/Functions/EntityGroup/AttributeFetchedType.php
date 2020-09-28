<?php

namespace Espo\Core\Formula\Functions\EntityGroup;

use \Espo\ORM\Entity;
use \Espo\Core\Exceptions\Error;

class AttributeFetchedType extends AttributeType
{
    protected function getAttributeValue($attribute)
    {
        return $this->attributeFetcher->fetch($this->getEntity(), $attribute, true);
    }
}