<?php

namespace Espo\Core\Formula\Functions\EntityGroup;

use \Espo\ORM\Entity;
use \Espo\Core\Exceptions\Error;

class AttributeType extends \Espo\Core\Formula\Functions\AttributeType
{
    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value')) {
            throw new Error();
        }

        if (!is_array($item->value)) {
            throw new Error();
        }

        if (count($item->value) < 1) {
            throw new Error();
        }

        $attribute = $this->evaluate($item->value[0]);

        return $this->getAttributeValue($attribute);
    }
}