<?php

namespace Espo\Core\Formula\Functions\ArrayGroup;

use \Espo\Core\Exceptions\Error;

class IncludesType extends \Espo\Core\Formula\Functions\Base
{
    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value') || !is_array($item->value)) {
            throw new Error('Value for \'Array\\Includes\' item is not array.');
        }
        if (count($item->value) < 2) {
            throw new Error('Bad arguments passed to \'Array\\Includes\'.');
        }
        $list = $this->evaluate($item->value[0]);
        $needle = $this->evaluate($item->value[1]);

        if (!is_array($list)) {
            return false;
        }

        return in_array($needle, $list);
    }
}