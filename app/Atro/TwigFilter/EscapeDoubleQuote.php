<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\TwigFilter;

class EscapeDoubleQuote extends EscapeString
{
    public function filter($value)
    {
        if (empty($value)) {
            return null;
        }

        return $this->escapeDoubleQuote($value);
    }
}
