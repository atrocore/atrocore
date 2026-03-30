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

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;


class Selection extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
            if ($entity->get('type') === 'single' && count($this->getEntities($entity->id)) > 1) {
                throw new BadRequest($this->getLanguage()->translate('cannotSetToSingle', 'messages', 'Selection'));
            }
        }

        parent::beforeSave($entity, $options);
    }

    public function getEntities(string $selectionId): array
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->from('selection_item', 'sr')
            ->select('distinct sr.entity_name')
            ->join('sr', 'selection', 's', 'sr.selection_id = s.id')
            ->where('s.id = :selectionId')
            ->setParameter('selectionId', $selectionId)
            ->fetchAllAssociative();

        return array_column($result, 'entity_name');
    }

    public function createActivityNote(
        string $parentId,
        string $parentType,
        string $noteType,
        string $action,
        string $relatedType = '',
        string $relatedId = '',
        array $extraData = []
    ): void {
        try {
            $data = new \stdClass();
            $data->action = $action;
            foreach ($extraData as $key => $value) {
                $data->$key = $value;
            }

            // Save parentName at creation time
            $data->parentName = $this->getEntityDisplayName($parentType, $parentId);

            $note = $this->getEntityManager()->getEntity('Note');
            $note->set('type', $noteType);
            $note->set('parentId', $parentId);
            $note->set('parentType', $parentType);
            $note->set('data', $data);
            if ($relatedType !== '' && $relatedId !== '') {
                // Save relatedName at creation time
                $data->relatedName = $this->getEntityDisplayName($relatedType, $relatedId);
                $note->set('data', $data);
                $note->set('relatedType', $relatedType);
                $note->set('relatedId', $relatedId);
            }
            $this->getEntityManager()->saveEntity($note);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("Failed to create $noteType note '$action': " . $e->getMessage());
        }
    }

    private function getEntityDisplayName(string $entityType, string $entityId): ?string
    {
        try {
            $nameField = $this->getMetadata()->get(['scopes', $entityType, 'nameField']) ?? 'name';
            $fieldDefs = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $nameField]);
            if (empty($fieldDefs)) {
                return null;
            }
            $entity = $this->getEntityManager()->getRepository($entityType)
                ->select(['id', $nameField])
                ->where(['id' => $entityId])
                ->findOne();
            if ($entity) {
                return $entity->get($nameField);
            }
        } catch (\Throwable $e) {
            // silently ignore
        }
        return null;
    }

}
