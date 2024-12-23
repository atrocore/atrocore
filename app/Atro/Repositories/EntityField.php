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

use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity as OrmEntity;

class EntityField extends ReferenceData
{
    protected function getAllItems(array $params = []): array
    {
        $items = [];
        foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $row) {
            if (empty($row['fields'])) {
                continue;
            }

            foreach ($row['fields'] as $fieldName => $fieldDefs) {
                if (!empty($fieldDefs['emHidden'])) {
                    continue;
                }

                $items[] = array_merge($row, [
                    'id'         => "{$entity}_{$fieldName}",
                    'code'       => $fieldName,
                    'name'       => $this->getLanguage()->translate($fieldName, 'fields', $entity),
                    'entityId'   => $entity,
                    'entityName' => $this->getLanguage()->translate($entity, 'scopeNames'),
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
