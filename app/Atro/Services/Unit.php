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

namespace Atro\Services;

use Atro\Core\Templates\Services\Base;
use Atro\Core\EventManager\Event;
use Espo\ORM\Entity;

class Unit extends Base
{
    public function setUnitAsDefault(Entity $unit): void
    {
        $needToSave = false;
        $measureId = $unit->get('measureId');
        foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if (!empty($fieldDefs['measureId']) && $fieldDefs['measureId'] == $measureId) {
                    if (!empty($fieldDefs['defaultUnit']) && $fieldDefs['defaultUnit'] == $unit->get('id')) {
                        continue;
                    }

                    $needToSave = true;
                    $this->getMetadata()->set('entityDefs', $entity, [
                        'fields' => [
                            "$field" => [
                                'defaultUnit' => $unit->get('id')
                            ]
                        ]
                    ]);
                }
            }
        }

        if ($needToSave) {
            $this->getMetadata()->save();
            $this->getInjection('dataManager')->clearCache();
        }

        $this->dispatchEvent('afterSetUnitAsDefault', new Event(['entity' => $unit, 'service' => $this]));
    }

    protected function init()
    {
        parent::init();
        $this->addDependency('dataManager');
    }
}
