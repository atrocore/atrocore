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

namespace Atro\Core\AnonymizationResolvers;

use Atro\Core\Container;
use Atro\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

abstract class AbstractAnonymizationResolver
{
    public const LINK_TYPES = ['link', 'linkMultiple', 'file'];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Checks whether the field can be anonymized by this resolver
     */
    abstract public function supports(Entity $entity, string $field): bool;

    /**
     * Anonymize the value of the field in the record.
     *
     * @param array $processed records that are already erased, prevents endless loops for cascade erasure
     *
     * @return bool whether the value of the field is erased
     */
    abstract public function resolve(Entity $entity, string $field, array &$processed = []): bool;

    protected function getFieldDefs(Entity $entity, string $field): array
    {
        return $entity->entityDefs['fields'][$field] ?? $this->getMetadata()->get(['entityDefs', $entity->getEntityName(), 'fields', $field], []);
    }

    protected function getActualFields(Entity $entity, string $field): array
    {
        $fields = [];

        $fieldDefs = $this->getFieldDefs($entity, $field);
        if (empty($fieldDefs['type'])) {
            return $fields;
        }

        foreach ($this->getMetadata()->get(['fields', $fieldDefs['type'], 'actualFields'], []) as $suffix) {
            $fields[] = $field . ucfirst($suffix);
        }

        return $fields;
    }

    protected function nullify(Entity $entity, string $field): void
    {
        $defs = $this->getFieldDefs($entity, $field);

        $value = ($defs['type'] ?? null) === 'bool' && empty($defs['allowNullForBool']) ? false : null;

        foreach ($this->getActualFields($entity, $field) as $attribute) {
            $entity->set($attribute, $value);
        }
    }

    protected function getForeignEntityType(Entity $entity, string $field): ?string
    {
        return $this->getMetadata()->get(['entityDefs', $entity->getEntityName(), 'links', $field, 'entity']);
    }

    protected function getLinkedEntities(Entity $entity, string $field): array
    {
        if (($this->getFieldDefs($entity, $field)['type'] ?? null) === 'linkMultiple') {
            return iterator_to_array(
                $this->getEntityManager()->getRepository($entity->getEntityName())->findRelated($entity, $field)
            );
        }

        $foreignId = $entity->get($field . 'Id');
        $foreignEntityType = $this->getForeignEntityType($entity, $field);
        if (empty($foreignId) || empty($foreignEntityType)) {
            return [];
        }

        $foreign = $this->getEntityManager()->getEntity($foreignEntityType, $foreignId);

        return !empty($foreign) ? [$foreign] : [];
    }

    protected function unlink(Entity $entity, string $field, array $linkedEntities): void
    {
        $fieldDefs = $this->getFieldDefs($entity, $field);

        if (($fieldDefs['type'] ?? null) === 'linkMultiple') {
            $repository = $this->getEntityManager()->getRepository($entity->getEntityName());

            foreach ($linkedEntities as $foreign) {
                $repository->unrelate($entity, $field, $foreign);
            }

            return;
        }

        $entity->set($field . 'Id', null);
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }
}
