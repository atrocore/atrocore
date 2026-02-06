<?php
/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core;

use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Espo\ORM\Entity;

class FieldMetadataManager
{
    private const array EXCLUDED_FIELDS = ['id', 'deleted', 'createdAt', 'modifiedAt', 'createdBy', 'modifiedBy'];

    public function __construct(
        protected readonly Metadata $metadata,
        protected readonly Connection $connection,
    ) {}

    public function prepareEntityMetadata(Entity $entity): void
    {
        if (!$this->entityHasMetadata($entity->getEntityName())) {
            return;
        }

        if ($entity->_metadataLoaded ?? false) {
            return;
        }

        $results = $this->getConnection()->createQueryBuilder()
            ->select('id', 'field', 'locked')
            ->from($this->getConnection()->quoteIdentifier(Util::toUnderScore($entity->getEntityName()) . '_metadata'))
            ->where('entity_id=:id')
            ->setParameter('id', $entity->get('id'))
            ->executeQuery()
            ->fetchAllAssociative();

        $results = array_column($results, null, 'field');

        foreach ($results as $field => $meta) {
            if (!$this->fieldHasMetadata($entity, $field)) {
                continue;
            }

            $entity->setMeta('locked', $field, $meta['locked']);
        }

        $entity->_metadataLoaded = true;
    }

    public function saveFieldMetadata(Entity $entity, string $fieldName): void
    {
        if (!$this->getMetadata()->get(['scopes', $entity->getEntityName(), 'enableFieldValueLock'])) {
            return;
        }

        $tableName = Util::toUnderScore($entity->getEntityName()) . '_metadata';
        $sql = "INSERT INTO $tableName (id, entity_id, field, locked) VALUES (:id, :entityId, :field, :locked)";
        if (Converter::isPgSQL($this->getConnection())) {
            $sql .= " ON CONFLICT (deleted, entity_id, field) DO UPDATE SET locked = EXCLUDED.locked RETURNING xmax";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE locked = VALUES(locked)";
        }

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->bindValue('id', IdGenerator::uuid());
        $stmt->bindValue('entityId', $entity->get('id'));
        $stmt->bindValue('field', $fieldName);
        $stmt->bindValue('locked', $entity->getMeta('locked', $fieldName), \Doctrine\DBAL\ParameterType::BOOLEAN);
        $stmt->executeStatement();
    }

    public function entityHasMetadata(string $entityName): bool
    {
        return $this->getMetadata()->get(['scopes', $entityName, 'enableFieldValueLock'], false);
    }

    public function fieldHasMetadata(Entity $entity, string $field): bool
    {
        $entityName = $entity->getEntityName();

        return $this->getMetadata()->get(['scopes', $entityName, 'enableFieldValueLock']) &&
            !$this->getMetadata()->get(['entityDefs', $entityName, 'fields', $field, 'disableFieldValueLock']) &&
            !$this->isVirtualField($entity, $field) && !in_array($field, self::EXCLUDED_FIELDS);
    }

    private function isVirtualField(Entity $entity, string $field): bool
    {
        $originalField = $field;
        foreach (['Name', 'Names', 'Id', 'Ids', 'OptionsData'] as $suffix) {
            if (str_ends_with($field, $suffix)) {
                $originalField = substr($field, 0, -strlen($suffix));
            }
        }

        return ($entity->entityDefs['fields'][$originalField] ?? null) && $originalField !== $field;
    }

    protected function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    protected function getConnection(): Connection
    {
        return $this->connection;
    }
}