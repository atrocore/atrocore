<?php

declare(strict_types=1);

namespace Treo\Core\Utils;

/**
 * Class Random
 *
 * @package Treo\Core\Utils
 */
class Random
{
    const CHARACTERS = "0123456789abcdefghijklmnopqrstuvwxyz";

    /**
     * @param int $length
     *
     * @return string
     */
    public static function getString(int $length): string
    {
        $string = '';
        $strLength = strlen(self::CHARACTERS) - 1;

        for ($i = 0; $i < $length; $i++) {
            $string .= self::CHARACTERS[rand(0, $strLength)];
        }

        return $string;
    }
}
