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

use Atro\Core\Twig\AbstractTwigFilter;
use Espo\Core\ServiceFactory;

use Espo\ORM\Entity;

class PrepareEntity extends AbstractTwigFilter
{
    protected ServiceFactory $serviceFactory;

    public function __construct(ServiceFactory $serviceFactory)
    {
        $this->serviceFactory = $serviceFactory;
    }

    public function filter($value)
    {
        if (empty($value) || !is_object($value) || !($value instanceof Entity)) {
            return null;
        }

        $service = $this->serviceFactory->create($value->getEntityType());
        $service->prepareEntityForOutput($value);

        return $value;
    }
}
