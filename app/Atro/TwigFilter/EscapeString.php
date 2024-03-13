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

namespace Atro\TwigFilter;

use Atro\Core\Twig\AbstractTwigFilter;

class EscapeString extends AbstractTwigFilter
{
    public function filter($value)
    {
        if (empty($value)) {
            return null;
        }

        return $this->escapeDoubleQuote($this->backslashNToBr($value));
    }

    protected function escapeDoubleQuote(string $value): string
    {
        return str_replace('"', '\"', $value);
    }

    protected function backslashNToBr(string $value): string
    {
        return str_replace("\n", '<br>', $value);
    }
}
