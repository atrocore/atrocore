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

use Atro\Services\Record;
use Espo\ORM\Entity;

/**
 * A subtype record is the supertype record extended with the subtype fields, the repository splits the data
 * between both tables.
 */
class Subtype extends Record
{
    protected function filterInput($data, ?Entity $entity = null)
    {
        parent::filterInput($data, $entity);

        if (!is_object($data)) {
            return;
        }

        // the supertype record is linked by the repository and the subtype is defined by the entity itself
        unset($data->supertypeId, $data->supertypeName, $data->type);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $this->loadSupertypeLinkNames($entity);
    }

    /**
     * The names of the records linked to the supertype record are not joined, they are loaded separately.
     */
    protected function loadSupertypeLinkNames(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $defs) {
            if (empty($defs['supertypeField']) || ($defs['type'] ?? null) !== 'link') {
                continue;
            }

            $id = $entity->get($field . 'Id');
            if (empty($id) || !empty($entity->get($field . 'Name'))) {
                continue;
            }

            $foreignEntity = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'entity']);
            if (empty($foreignEntity)) {
                continue;
            }

            $record = $this->getEntityManager()->getEntity($foreignEntity, $id);
            if (!empty($record)) {
                $entity->set($field . 'Name', $record->get($this->getNameField($foreignEntity)));
            }
        }
    }
}
