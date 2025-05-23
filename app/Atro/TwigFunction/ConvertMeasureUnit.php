<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\ORM\EntityManager;

class ConvertMeasureUnit extends AbstractTwigFunction
{

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function run($value, string $measureId, string $unitId): array
    {
        return $this->entityManager->getRepository('Measure')->convertMeasureUnit($value, $measureId, $unitId);
    }
}