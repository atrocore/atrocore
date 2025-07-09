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

namespace Atro\Core\Templates\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Services\MassActions;
use Atro\Services\Record;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

class Base extends Record
{


    public function duplicateAndLinkEntity($id)
    {
    }

    /**
     * @param Entity $record
     * @param Entity $duplicatingRecord
     */
    protected function duplicateAssociatedMainRecords(Entity $record, Entity $duplicatingRecord)
    {
        // get data
        $data = $duplicatingRecord->get('associatedMainRecords');

        // copy
        if (count($data) > 0) {
            foreach ($data as $row) {
                $item = $row->toArray();
                $item['id'] = Util::generateId();
                $item['mainRecordId'] = $record->get('id');
                $item['backwardAssociatedRecordId'] = null;

                // prepare entity
                $entity = $this->getEntityManager()->getEntity('AssociatedRecord');
                $entity->set($item);

                // save
                $this->getEntityManager()->saveEntity($entity);
            }
        }
    }

    /**
     * @param Entity $record
     * @param Entity $duplicatingRecord
     */
    protected function duplicateAssociatedRelatedRecord(Entity $record, Entity $duplicatingRecord)
    {
        // get data
        $data = $duplicatingRecord->get('associatedRelatedRecord');

        // copy
        if (count($data) > 0) {
            foreach ($data as $row) {
                $item = $row->toArray();
                $item['id'] = Util::generateId();
                $item['relatedRecordId'] = $record->get('id');
                $item['backwardAssociatedRecordId'] = null;

                // prepare entity
                $entity = $this->getEntityManager()->getEntity('AssociatedRecord');
                $entity->set($item);

                // save
                $this->getEntityManager()->saveEntity($entity);
            }
        }
    }

    /**
     * @return MassActions
     */
    protected function getMassActionsService(): MassActions
    {
        return $this->getServiceFactory()->create('MassActions');
    }
}
