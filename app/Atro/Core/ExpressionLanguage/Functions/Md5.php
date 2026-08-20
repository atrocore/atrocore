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

class Md5 implements FunctionInterface
{
    public function evaluate(mixed ...$arguments): mixed
    {
        return md5((string)$arguments[0]);
    }

    public function compile(string ...$arguments): string
    {
        return sprintf('md5((string)%1$s)', $arguments[0]);
    }
}
