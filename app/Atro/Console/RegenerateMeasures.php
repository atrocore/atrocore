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

namespace Atro\Console;

use Espo\ORM\EntityManager;

class RegenerateMeasures extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Regenerate system measures.';
    }

    public function run(array $data): void
    {
        $this->refresh();
        $this->getContainer()->get('dataManager')->clearCache();

        self::show('Measures regenerated successfully.', self::SUCCESS);
    }

    public function refresh(): void
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        foreach ($this->getMetadata()->get(['app', 'measures'], []) as $measureData) {
            $measure = $em->getRepository('Measure')->get($measureData['id']);
            if (!empty($measure)) {
                continue;
            }
            $measure = $em->getRepository('Measure')->get();
            $measure->id = $measureData['id'];
            $measure->set($measureData);

            try {
                $em->saveEntity($measure);
            } catch (\Throwable $e) {
                // ignore all
            }
        }

        foreach ($this->getMetadata()->get(['app', 'units'], []) as $unitData) {
            $unit = $em->getRepository('Unit')->get($unitData['id']);
            if (!empty($unit)) {
                continue;
            }
            
            $unit = $em->getRepository('Unit')->get();
            $unit->id = $unitData['id'];
            $unit->set($unitData);

            try {
                $em->saveEntity($unit);
            } catch (\Throwable $e) {
                // ignore all
            }
        }
    }
}
