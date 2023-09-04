<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.md, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\ORM\Entity;

class GetFetched extends AbstractTwigFunction
{
    /**
     * @param Entity $entity
     * @param string $field
     *
     * @return mixed
     */
    public function run(...$input)
    {
        if (empty($input[0]) || empty($input[1])) {
            return null;
        }

        $entity = $input[0];
        $field = $input[1];

        return $entity->getFetched($field);
    }
}
