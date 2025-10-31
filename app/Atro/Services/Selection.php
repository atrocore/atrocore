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

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Selection extends Base
{
    public function createSelectionWithRecords(string $scope, array $entityIds)
    {

        $selection = $this->getEntityManager()->getEntity('Selection');
        $this->getEntityManager()->saveEntity($selection);

        foreach ($entityIds as $entityId) {
            $record = $this->getEntityManager()->getEntity('SelectionRecord');
            $record->set('entityId', $entityId);
            $record->set('entityType', $scope);
            $this->getEntityManager()->saveEntity($record);

            $ssr = $this->getEntityManager()->getEntity('SelectionSelectionRecord');
            $ssr->set('selectionRecordId', $record->get('id'));
            $ssr->set('selectionId', $selection->get('id'));

            $this->getEntityManager()->saveEntity($ssr);
        }

        return $selection;
    }
}
