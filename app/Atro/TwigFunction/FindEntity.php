<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\Core\ORM\EntityManager;

class FindEntity extends AbstractTwigFunction
{
    protected EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function run(string $entityName, array $where)
    {
        if (empty($input[0]) || empty($input[1])) {
            return null;
        }

        return $this->entityManager->getRepository($entityName)
            ->where($where)
            ->findOne();
    }
}
