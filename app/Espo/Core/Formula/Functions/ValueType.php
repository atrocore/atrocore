<?php

namespace Espo\Core\Formula\Functions;

use \Espo\ORM\Entity;
use \Espo\Core\Exceptions\Error;

class ValueType extends Base
{
    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value')) {
            throw new Error();
        }

        $value = $item->value;

        if (is_string($value)) {
            $value = str_replace("\\n", "\n", $value);
        }

        return $value;
    }
}