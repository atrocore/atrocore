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

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class RegenerateUiHandlers extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Regenerate ui handlers.';
    }

    public function run(array $data): void
    {
        $this->refresh();
        $this->getContainer()->get('dataManager')->clearCache();

        self::show('UI handlers regenerated successfully.', self::SUCCESS);
    }

    public function refresh(): void
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        foreach ($this->getMetadata()->get('clientDefs', []) as $entityType => $clientDefs) {
            if (empty($clientDefs['dynamicLogic']['fields'])) {
                continue;
            }

            foreach ($clientDefs['dynamicLogic']['fields'] as $field => $fieldConditions) {
                foreach ($fieldConditions as $type => $fieldData) {
                    if (empty($fieldData['conditionGroup'])) {
                        continue;
                    }

                    $id = strtolower("g_{$entityType}_{$field}_{$type}");

                    $entity = $em->getRepository('ScreenLogic')->get($id);
                    if (!empty($measure)) {
                        continue;
                    }

                    $typeId = null;

                    switch ($type) {
                        case 'readOnly':
                            $typeId = 'dl_read_only';
                            break;
                        case 'visible':
                            $typeId = 'dl_visible';
                            break;
                        case 'required':
                            $typeId = 'dl_required';
                            break;
                    }

                    $entity = $em->getRepository('ScreenLogic')->get();
                    $entity->id = $id;
                    $entity->set([
                        'name'           => "Make field '{$field}' {$type}",
                        'entityType'     => $entityType,
                        'field'          => $field,
                        'type'           => $typeId,
                        'conditionsType' => 'basic',
                        'conditions'     => json_encode($fieldData['conditionGroup'])
                    ]);

                    echo '<pre>';
                    print_r($entity->toArray());
                    die();

                    try {
                        $em->saveEntity($entity);
                    } catch (\Throwable $e) {
                        // ignore all
                    }

                    echo '<pre>';
                    print_r($fieldData);
                    die();
                }
            }

            echo '<pre>';
            print_r($clientDefs['dynamicLogic']);
            die();

        }

//        /** @var EntityManager $em */
//        $em = $this->getContainer()->get('entityManager');
//
//        foreach ($this->getMetadata()->get(['app', 'measures'], []) as $measureData) {
//            $measure = $em->getRepository('Measure')->get($measureData['id']);
//            if (!empty($measure)) {
//                continue;
//            }
//            $measure = $em->getRepository('Measure')->get();
//            $measure->id = $measureData['id'];
//            $measure->set($measureData);
//
//            try {
//                $em->saveEntity($measure);
//            } catch (\Throwable $e) {
//                // ignore all
//            }
//        }
//
//        foreach ($this->getMetadata()->get(['app', 'units'], []) as $unitData) {
//            $unit = $em->getRepository('Unit')->get($unitData['id']);
//            if (!empty($unit)) {
//                continue;
//            }
//
//            $unit = $em->getRepository('Unit')->get();
//            $unit->id = $unitData['id'];
//            $unit->set($unitData);
//
//            try {
//                $em->saveEntity($unit);
//                if ($unit->get('measureId') === 'currency') {
//                    $this->calculateMultiplier($unit);
//                }
//            } catch (\Throwable $e) {
//                // ignore all
//            }
//        }
    }
}
