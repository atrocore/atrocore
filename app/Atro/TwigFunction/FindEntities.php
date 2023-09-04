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
use Espo\ORM\EntityCollection;

class FindEntities extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('entityManager');
    }

    /**
     * @param string $entityName
     * @param array  $where
     * @param string $orderField
     * @param string $orderDirection
     * @param int    $offset
     * @param int    $limit
     *
     * @return EntityCollection
     */
    public function run(...$input)
    {
        if (empty($input[0])) {
            return null;
        }

        $entityName = $input[0];
        $where = $input[1] ?? [];
        $orderField = $input[2] ?? 'id';
        $orderDirection = $input[3] ?? 'ASC';
        $offset = $input[4] ?? 0;
        $limit = $input[5] ?? \PHP_INT_MAX;

        return $this->getInjection('entityManager')->getRepository($entityName)
            ->where((array)$where)
            ->order((string)$orderField, (string)$orderDirection)
            ->limit((int)$offset, (int)$limit)
            ->find();
    }
}
