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

class GenerateFontColor extends AbstractTwigFilter
{
    public function filter($value)
    {
        if (empty($value)) {
            return null;
        }

        $color = '#000';

        $value = substr($value, 1);
        $r = hexdec(substr($value, 0, 2));
        $g = hexdec(substr($value, 2, 2));
        $b = hexdec(substr($value, 4, 2));
        $l = 1 - (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        if ($l >= 0.5) {
            $color = '#fff';
        }

        return $color;
    }
}
