<?php

namespace Espo\Core\Formula\Functions\NumberGroup;

use \Espo\Core\Exceptions\Error;

class RoundType extends \Espo\Core\Formula\Functions\Base
{
    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value')) {
            return true;
        }

        if (!is_array($item->value)) {
            throw new Error();
        }

        if (count($item->value) < 1) {
             throw new Error();
        }

        $value = $this->evaluate($item->value[0]);

        $precision = 0;
        if (count($item->value) > 1) {
             $precision = $this->evaluate($item->value[1]);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return round($value, $precision);
    }
}