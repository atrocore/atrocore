<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Utils;

class RegexUtil
{
    /**
     * Validate a regex pattern stored without delimiters.
     */
    public static function validate(string $pattern): bool
    {
        set_error_handler(static function () {}, E_WARNING);
        $result = @preg_match(self::toPhpPattern($pattern), '') !== false;
        restore_error_handler();
        return $result;
    }

    /**
     * Wrap a delimiter-free pattern in the // delimiters required by PHP's preg_* functions.
     */
    public static function toPhpPattern(string $pattern): string
    {
        return '~' . str_replace('~', '\~', $pattern) . '~';
    }

    /**
     * Strip leading and trailing // delimiters from a legacy stored pattern.
     * Returns the pattern unchanged if it has no delimiters.
     */
    public static function stripDelimiters(string $pattern): string
    {
        if (preg_match('/^\/(.*)\/([gmixsuAJD]*)$/', $pattern, $matches)) {
            return $matches[1];
        }
        return $pattern;
    }
}
