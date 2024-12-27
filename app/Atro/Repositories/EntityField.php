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
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity as OrmEntity;

class EntityField extends ReferenceData
{
    protected function getAllItems(array $params = []): array
    {
        $entities = [];

        $entityName = $params['whereClause'][0]['entityId='] ?? null;

        if (!empty($entityName)) {
            $entities[] = $entityName;
        } else {
            foreach ($this->getEntityManager()->getRepository('Entity')->find() as $entity) {
                $entities[] = $entity->get('code');
            }
        }

        $boolFields = [];
        foreach ($this->getMetadata()->get(['entityDefs', 'EntityField', 'fields']) as $field => $defs) {
            if ($defs['type'] === 'bool') {
                $boolFields[] = $field;
            }
        }

        $items = [];
        foreach ($entities as $entityName) {
            foreach ($this->getMetadata()->get(['entityDefs', $entityName, 'fields'], []) as $fieldName => $fieldDefs) {
                if (!empty($fieldDefs['emHidden'])) {
                    continue;
                }

                foreach ($boolFields as $boolField) {
                    $fieldDefs[$boolField] = !empty($fieldDefs[$boolField]);
                }

                $items[] = array_merge($fieldDefs, [
                    'id'          => "{$entityName}_{$fieldName}",
                    'code'        => $fieldName,
                    'name'        => $this->getLanguage()->translate($fieldName, 'fields', $entityName),
                    'entityId'    => $entityName,
                    'entityName'  => $this->getLanguage()->translate($entityName, 'scopeNames'),
                    'tooltipText' => !empty($fieldDefs['tooltip']) ? $this->getLanguage()->translate($fieldName, 'tooltips', $entityName) : null,
                    'tooltipLink' => !empty($fieldDefs['tooltip']) ? $fieldDefs['tooltipLink'] : null,
                ]);
            }
        }

        return $items;
    }

    public function insertEntity(OrmEntity $entity): bool
    {
        return true;
    }

    public function updateEntity(OrmEntity $entity): bool
    {
        if ($entity->isAttributeChanged('code')) {
            throw new BadRequest("Code cannot be changed.");
        }

        if ($entity->isAttributeChanged('type')) {
            throw new BadRequest("Type cannot be changed.");
        }

        if ($entity->isAttributeChanged('entityId')) {
            throw new BadRequest("Entity cannot be changed.");
        }

        return true;
    }

    public function deleteEntity(OrmEntity $entity): bool
    {
        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function getLanguage(): \Atro\Core\Utils\Language
    {
        return $this->getInjection('language');
    }
}
