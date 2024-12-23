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
//        $boolFields = [];
//        foreach ($this->getMetadata()->get(['entityDefs', 'Entity', 'fields']) as $field => $defs) {
//            if ($defs['type'] === 'bool') {
//                $boolFields[] = $field;
//            }
//        }

        $items = [];
//        foreach ($this->getMetadata()->get('scopes', []) as $code => $row) {
//            if (!empty($row['emHidden'])) {
//                continue;
//            }
//
//            foreach ($boolFields as $boolField) {
//                $row[$boolField] = !empty($row[$boolField]);
//            }
//
//            $items[] = array_merge($row, [
//                'id'                    => $code,
//                'code'                  => $code,
//                'name'                  => $this->getLanguage()->translate($code, 'scopeNames'),
//                'namePlural'            => $this->getLanguage()->translate($code, 'scopeNamesPlural'),
//                'iconClass'             => $this->getMetadata()->get(['clientDefs', $code, 'iconClass']),
//                'kanbanViewMode'        => $this->getMetadata()->get(['clientDefs', $code, 'kanbanViewMode']),
//                'clearDeletedAfterDays' => $this->getMetadata()->get(['scopes', $code, 'clearDeletedAfterDays'], 60),
//                'color'                 => $this->getMetadata()->get(['clientDefs', $code, 'color']),
//                'sortBy'                => $this->getMetadata()->get(['entityDefs', $code, 'collection', 'sortBy']),
//                'sortDirection'         => $this->getMetadata()
//                    ->get(['entityDefs', $code, 'collection', 'asc']) ? 'asc' : 'desc',
//                'textFilterFields'      => $this->getMetadata()
//                    ->get(['entityDefs', $code, 'collection', 'textFilterFields']),
//            ]);
//        }

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
}
