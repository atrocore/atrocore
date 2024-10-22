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

use Atro\Core\Templates\Services\ReferenceData;
use Espo\ORM\Entity;

class Store extends ReferenceData
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if ($entity->get('code') === 'atrocore/core') {
            $entity->set('status', 'installed');
            $entity->set('currentVersion', '1.10.71-rc2');
            $entity->set('latestVersion', '1.11.18');
            $entity->set('settingVersion', '^1.11.21');
            $entity->set('isSystem', true);
            $entity->set('isComposer', true);
        }
    }
}
