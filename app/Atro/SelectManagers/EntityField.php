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

namespace Atro\SelectManagers;

use Atro\Core\SelectManagers\Base;

class EntityField extends Base
{
    protected function boolFilterExportable(array &$result): void
    {
        $result['skipEmHidden']  = true;
        $result['whereClause'][] = ['exportDisabled!=' => true];
    }

    protected function boolFilterMassUpdatable(array &$result): void
    {
        $result['skipEmHidden']  = true;
        $result['whereClause'][] = ['massUpdateDisabled!=' => true];
    }

    /**
     * The fields of the subtype given in the data of the filter that may be shown on its supertype, named
     * "<Subtype>_<field>" there.
     */
    protected function boolFilterSubtypeFieldsShownOnSupertype(array &$result): void
    {
        $subtype = (string)$this->getBoolFilterParameter('subtypeFieldsShownOnSupertype');
        $supertype = (string)$this->getMetadata()->get(['scopes', $subtype, 'supertypeId']);

        // a code no field has, so that nothing is found when the subtype has no such fields
        $codes = [''];
        if ($supertype !== '') {
            foreach (array_keys($this->getMetadata()->get(['entityDefs', $subtype, 'fields'], [])) as $field) {
                $subtypeField = $this->getMetadata()->getSubtypeField($supertype, "{$subtype}_{$field}");
                if ($subtypeField !== null && $subtypeField['field'] === $field) {
                    $codes[] = $field;
                }
            }
        }

        $result['whereClause'][] = ['entityId' => $subtype];
        $result['whereClause'][] = ['code' => $codes];
    }

}
