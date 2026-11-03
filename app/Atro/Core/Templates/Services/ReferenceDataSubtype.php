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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Repositories\ReferenceDataSubtype as SubtypeRepository;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;

/**
 * A subtype record of a reference data entity is the supertype record with the fields of its type only. All the logic
 * belongs to the service of the supertype, the subtype limits the fields of the records to the fields of its type.
 */
class ReferenceDataSubtype extends ReferenceData
{
    public function readEntity(string $id, ?string $withRelationships = null): ?IEntity
    {
        return $this->toSubtypeRecord($this->getSupertypeService()->readEntity($id, $withRelationships));
    }

    public function getEntity(string $id, ?string $withRelationships = null): ?IEntity
    {
        return $this->toSubtypeRecord($this->getSupertypeService()->getEntity($id, $withRelationships));
    }

    public function prepareEntityById(string $id, bool $checkAcl = false, bool $useCache = false, ?string $withRelationships = null): ?Entity
    {
        return $this->toSubtypeRecord($this->getSupertypeService()->prepareEntityById($id, $checkAcl, $useCache, $withRelationships));
    }

    public function createEntity(\stdClass $attachment): string
    {
        $data = $this->filterSubtypeInput($attachment);
        $data->type = $this->getSubtypeRepository()->getTypeOption();

        return $this->getSupertypeService()->createEntityViaSubtype($data);
    }

    public function updateEntity(string $id, \stdClass $data): bool
    {
        $this->checkRecordOfSubtype($id);

        return $this->getSupertypeService()->updateEntity($id, $this->filterSubtypeInput($data));
    }

    public function findEntities($params)
    {
        $params['where'][] = [
            'type'      => 'equals',
            'attribute' => 'type',
            'value'     => $this->getSubtypeRepository()->getTypeOption(),
        ];

        $result = $this->getSupertypeService()->findEntities($params);

        if (isset($result['collection'])) {
            $entities = [];
            foreach ($result['collection'] as $record) {
                $entities[] = $this->getSubtypeRepository()->createFromSupertypeRecord($record);
            }
            $result['collection'] = new EntityCollection($entities, $this->entityType);
        }

        return $result;
    }

    public function findLinkedEntities($id, $link, $params)
    {
        $this->checkRecordOfSubtype((string)$id);

        return $this->getSupertypeService()->findLinkedEntities($id, $link, $params);
    }

    public function linkEntity($id, $link, $foreignId)
    {
        $this->checkRecordOfSubtype((string)$id);

        return $this->getSupertypeService()->linkEntity($id, $link, $foreignId);
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        $this->checkRecordOfSubtype((string)$id);

        return $this->getSupertypeService()->unlinkEntity($id, $link, $foreignId);
    }

    public function unlinkAll(string $id, string $link): bool
    {
        $this->checkRecordOfSubtype($id);

        return $this->getSupertypeService()->unlinkAll($id, $link);
    }

    /**
     * The fields of the other types are not the fields of the subtype record, they are skipped. The service fields
     * (starting with "_") are kept.
     */
    protected function filterSubtypeInput(\stdClass $data): \stdClass
    {
        $entity = $this->getRepository()->get();

        $result = new \stdClass();
        foreach (get_object_vars($data) as $field => $value) {
            if (str_starts_with((string)$field, '_') || $entity->hasAttribute($field) || $entity->hasRelation($field)) {
                $result->$field = $value;
            }
        }

        return $result;
    }

    protected function toSubtypeRecord(?IEntity $record): ?IEntity
    {
        if (empty($record)) {
            return null;
        }

        if (!$this->getSubtypeRepository()->isRecordOfSubtype($record)) {
            throw new NotFound();
        }

        return $this->getSubtypeRepository()->createFromSupertypeRecord($record);
    }

    protected function checkRecordOfSubtype(string $id): void
    {
        if (empty($this->getRepository()->get($id))) {
            throw new NotFound();
        }
    }

    protected function getSubtypeRepository(): SubtypeRepository
    {
        return $this->getRepository();
    }

    protected function getSupertypeService(): ReferenceData
    {
        return $this->getServiceFactory()->create($this->getSubtypeRepository()->getSupertypeName());
    }
}
