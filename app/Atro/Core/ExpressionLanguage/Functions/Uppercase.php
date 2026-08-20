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

namespace Atro\Core\ExpressionLanguage\Functions;

class Uppercase implements FunctionInterface
{
    public function evaluate(mixed ...$arguments): mixed
    {
        return is_string($arguments[0]) ? strtoupper($arguments[0]) : $arguments[0];
    }

    public function compile(string ...$arguments): string
    {
        return sprintf('(is_string(%1$s) ? strtoupper(%1$s) : %1$s)', $arguments[0]);
    }
}
