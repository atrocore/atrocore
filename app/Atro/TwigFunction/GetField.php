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

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\ORM\Entity;

class GetField extends AbstractTwigFunction
{
    public function run(Entity $entity, string $field)
    {
        return $entity->get($field);
    }
}
