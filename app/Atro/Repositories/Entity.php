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
        $items = [];
        foreach ($this->getMetadata()->get('scopes', []) as $code => $row) {
            if (!empty($row['emHidden'])) {
                continue;
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
        //    [10] => hasAssignedUser
        //    [12] => hideFieldTypeFilters
        //    [13] => aclActionLevelListMap
        //    [15] => enabledCopyConfigurations
        //    [17] => notificationDisabled
        //    [19] => autoDeleteAfterDays
        //    [20] => clearDeletedAfterDays
        //    [21] => isActiveUnavailable
        //    [22] => hasOwner
        //    [23] => hasTeam
        //    [24] => module
        //    [25] => showInAdminPanel
        //    [26] => stream
        //    [28] => statusField
        //    [30] => multiParents
        //    [31] => dragAndDrop
        //    [32] => fieldValueInheritance
        //    [33] => relationInheritance
        //    [34] => disableHierarchy
        //    [35] => hasAccount
        //    [36] => deleteWithoutConfirmation
        //    [37] => multiParentsDisabled
        //    [38] => quickCreateListDisabled
        //    [39] => duplicatableRelations
        //    [40] => overviewFilters
        //    [41] => addRelationEnabled
        //    [42] => mandatoryUnInheritedFields
        //    [43] => unInheritedFields
        //    [44] => inheritedRelations
        //    [45] => mandatoryUnInheritedRelations
        //    [46] => unInheritedRelations
        //    [47] => importTypeSimple
        //    [48] => completeness
        //    [49] => nonComparableFields
        //    [50] => hasCompleteness
        //    [52] => aclActionList
        //    [53] => aclLevelList
        //    [55] => kanbanStatusIgnoreList
        //    [56] => disabledFieldsForCopyConfigurations
        //    [57] => aclPortal
        //    [58] => description
        //    [61] => hasActivities
        //    [62] => hasTasks
        //    [63] => isHierarchyEntity
        //    [64] => hideLastViewed
        //    [65] => aclPortalLevelList
        //    [66] => queryBuilderFilter
        //    [67] => modifiedExtendedRelations

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
