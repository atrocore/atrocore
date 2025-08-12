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

    public function run($value, string $measureId, string $fromUnitId, ?string $toUnitId = null): array|float|int|null
    {
        $repository = $this->entityManager->getRepository('Measure');
        $data = $repository->convertMeasureUnit($value, $measureId, $fromUnitId);
        if (empty($toUnitId)) {
            return $data;
        }

        $units = $repository->getMeasureUnits($measureId);
        if (!isset($units[$toUnitId])) {
            return null;
        }

        return $data[$units[$toUnitId]->get('name')] ?? null;
    }
}