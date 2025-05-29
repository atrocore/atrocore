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

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Container;
use Atro\Core\Twig\AbstractTwigFilter;
use Espo\ORM\Entity;

class PutAttributesToEntity extends AbstractTwigFilter
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function filter($value)
    {
        if (empty($value) || !is_object($value) || !($value instanceof Entity)) {
            return null;
        }

        $this->getAttributeFieldConverter()->putAttributesToEntity($value);
    }

    protected function getAttributeFieldConverter(): AttributeFieldConverter
    {
        return $this->container->get(AttributeFieldConverter::class);
    }
}
