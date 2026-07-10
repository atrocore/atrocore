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
use Atro\Core\Utils\IdGenerator;

class ToUuid extends AbstractTwigFilter
{
    public function __construct(private readonly IdGenerator $idGenerator)
    {
    }

    public function filter($value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $this->idGenerator->toUuid($value);
    }
}