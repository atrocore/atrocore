<?php

namespace Espo\Core\Formula\Functions\ArrayGroup;

use \Espo\Core\Exceptions\Error;

class PushType extends \Espo\Core\Formula\Functions\Base
{
    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value') || !is_array($item->value)) {
            throw new Error('Bad \'Array\\push\' definition.');
        }
        if (count($item->value) < 2) {
            throw new Error('Bad arguments passed to \'Array\\push\'.');
        }
        $list = $this->evaluate($item->value[0]);
        if (!is_array($list)) {
            return false;
        }

        foreach ($item->value as $i => $v) {
            if ($i === 0) continue;
            $element = $this->evaluate($item->value[$i]);
            $list[] = $element;
        }

        return $list;
    }
}