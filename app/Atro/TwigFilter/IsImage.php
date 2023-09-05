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

class IsImage extends AbstractTwigFilter
{
    public function __construct()
    {
        $this->addDependency('metadata');
    }

    public function filter($value)
    {
        if (empty($value) || !is_object($value) || !($value instanceof Entity) || $value->getEntityType() !== 'Asset') {
            return false;
        }

        $fileNameParts = explode('.', $value->get("file")->get('name'));
        $fileExt = strtolower(array_pop($fileNameParts));

        return in_array($fileExt, $this->getInjection('metadata')->get('dam.image.extensions', []));
    }
}
