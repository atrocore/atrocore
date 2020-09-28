<?php

namespace Espo\Core\Formula\Functions;

use \Espo\ORM\Entity;
use \Espo\Core\Exceptions\Error;

class ListType extends Base
{
    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value')) {
            throw new Error();
        }

        if (!is_array($item->value)) {
            throw new Error();
        }

        $result = [];
        foreach ($item->value as $item) {
            $result[] = $this->evaluate($item);
        }

        return $result;
    }
}