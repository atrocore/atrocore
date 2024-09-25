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
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;

class FindEntities extends AbstractTwigFunction
{
    protected EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function run(string $entityName, array $where = [], string $orderField = 'id', string $orderDirection = 'ASC', int $offset = 0, int $limit = \PHP_INT_MAX, bool $withDeleted = false): EntityCollection
    {
        return $this->entityManager->getRepository($entityName)
            ->where($where)
            ->order($orderField, $orderDirection)
            ->limit($offset, $limit)
            ->find(['withDeleted' => $withDeleted]);
    }
}
