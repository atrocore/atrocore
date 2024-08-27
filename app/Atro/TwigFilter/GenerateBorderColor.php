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

class GenerateBorderColor extends AbstractTwigFilter
{
    public function filter($value)
    {
        if (empty($value)) {
            return null;
        }

        $amt = -10;
        $num = hexdec(substr($value, 1));
        $r = ($num >> 16) + $amt;
        $b = (($num >> 8) & 0x00FF) + $amt;
        $g = ($num & 0x0000FF) + $amt;

        $r = max(0, min(255, $r));
        $b = max(0, min(255, $b));
        $g = max(0, min(255, $g));

        $color = str_pad(dechex($g | ($b << 8) | ($r << 16)), 6, '0', STR_PAD_LEFT);

        return "#".$color;
    }
}
