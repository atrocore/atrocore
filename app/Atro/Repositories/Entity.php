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

class Entity extends ReferenceData
{
    protected function getAllItems(array $params = []): array
    {
        $boolFields = [];
        foreach ($this->getMetadata()->get(['entityDefs', 'Entity', 'fields']) as $field => $defs) {
            if ($defs['type'] === 'bool') {
                $boolFields[] = $field;
            }
        }

        $items = [];
        foreach ($this->getMetadata()->get('scopes', []) as $code => $row) {
            if (!empty($row['emHidden'])) {
                continue;
            }

            foreach ($boolFields as $boolField) {
                $row[$boolField] = !empty($row[$boolField]);
            }

            $items[] = array_merge($row, [
                'id'         => $code,
                'code'       => $code,
                'name'       => $this->getInjection('language')->translate($code, 'scopeNames'),
                'namePlural' => $this->getInjection('language')->translate($code, 'scopeNamesPlural')
            ]);
        }

        return $items;
    }

    public function insertEntity(OrmEntity $entity): bool
    {
        return true;
    }

    public function updateEntity(OrmEntity $entity): bool
    {
        $customScopeData = [];
        $customFile = "data/metadata/scopes/{$entity->get('code')}.json";
        if (file_exists($customFile)) {
            $customScopeData = json_decode(file_get_contents($customFile), true);
        }

        $loadedData = json_decode(json_encode($this->getMetadata()->loadData(true)), true);
        $loadedScopeData = $loadedData['scopes'][$entity->get('code')] ?? $customScopeData;

        $diff = [];
        foreach ($entity->toArray() as $field => $value) {
            if (in_array($field, ['id', 'code', 'name', 'namePlural'])) {
                continue;
            }
            if (!array_key_exists($field, $loadedScopeData) || $value !== $loadedScopeData[$field]) {
                $diff[$field] = $value;
            }
        }

        if (!empty($loadedScopeData['module']) && $loadedScopeData['module'] === 'Custom') {
            $diff = array_merge($customScopeData, $diff);
        }

        $customDataJson = json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($customFile, $customDataJson);

        return true;
    }

    public function deleteEntity(OrmEntity $entity): bool
    {
        return true;
    }


    protected function saveDataToFile(array $data): bool
    {
        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
