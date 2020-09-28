<?php

namespace Espo\Core\Utils;

class NumberUtil
{
    protected $decimalMark;

    protected $thousandSeparator;

    public function __construct($decimalMark = '.', $thousandSeparator = ',')
    {
        $this->decimalMark = $decimalMark;
        $this->thousandSeparator = $thousandSeparator;
    }

    public function format($value, $decimals = null, $decimalMark = null, $thousandSeparator = null)
    {
        if (is_null($decimalMark)) {
            $decimalMark = $this->decimalMark;
        }
        if (is_null($thousandSeparator)) {
            $thousandSeparator = $this->thousandSeparator;
        }

        if (!is_null($decimals)) {
             return number_format($value, $decimals, $decimalMark, $thousandSeparator);
        } else {
            $s = strval($value);
            $arr = explode('.', $value);

            $r = '0';
            if (!empty($arr[0])) {
                $r = number_format(intval($arr[0]), 0, '.', $thousandSeparator);
            }

            if (!empty($arr[1])) {
                $r = $r . $decimalMark . $arr[1];
            }

            return $r;
        }
    }
}

