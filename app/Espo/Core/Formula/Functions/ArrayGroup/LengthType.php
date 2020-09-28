<?php

namespace Espo\Core\Formula\Functions\ArrayGroup;

use \Espo\Core\Exceptions\Error;

class LengthType extends \Espo\Core\Formula\Functions\Base
{
    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value')) {
            throw new Error();
        }

        $list = $this->evaluate($item->value[0]);

        if (!is_array($list)) {
            return 0;
        }

        return count($list);
    }
}