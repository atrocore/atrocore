<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\ORM\Entity;

class FindEntity extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('entityManager');
    }

    /**
     * @param string $entityName
     * @param array  $where
     *
     * @return Entity|null
     */
    public function run(...$input)
    {
        if (empty($input[0]) || empty($input[1])) {
            return null;
        }

        $entityName = $input[0];
        $where = $input[1];

        return $this->getInjection('entityManager')->getRepository($entityName)
            ->where((array)$where)
            ->findOne();
    }
}
