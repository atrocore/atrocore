<?php

namespace Espo\Core\Formula\Functions\StringGroup;

use \Espo\Core\Exceptions\Error;

class ContainsType extends \Espo\Core\Formula\Functions\Base
{
    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value') || !is_array($item->value)) {
            throw new Error('Value for \'String\\Contains\' item is not an array.');
        }
        if (count($item->value) < 2) {
            throw new Error('Bad arguments passed to \'String\\Contains\'.');
        }
        $string = $this->evaluate($item->value[0]);
        $needle = $this->evaluate($item->value[1]);

        if (!is_string($string)) {
            return false;
        }

        return mb_strpos($string, $needle) !== false;
    }
}