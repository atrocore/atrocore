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

namespace Atro\TwigFilter;

use Atro\Core\Twig\AbstractTwigFilter;

use Espo\ORM\Entity;

class PrepareEntity extends AbstractTwigFilter
{
    public function __construct()
    {
        $this->addDependency('serviceFactory');
    }

    public function filter($value)
    {
        if (empty($value) || !is_object($value) || !($value instanceof Entity)) {
            return null;
        }

        $service = $this->getInjection('serviceFactory')->create($value->getEntityType());
        $service->prepareEntityForOutput($value);

        return $value;
    }
}
