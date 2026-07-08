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

class Entity extends Base
{
    protected function boolFilterCanHasAttributes(&$result)
    {
        $result['whereClause'][] = [
            'canHasAttributes' => true
        ];
    }

    protected function boolFilterCanHasClassifications(&$result)
    {
        $result['whereClause'][] = [
            'canHasClassifications' => true
        ];
    }

    protected function boolFilterCanHasComponents(&$result)
    {
        $result['whereClause'][] = [
            'canHasComponents' => true
        ];
    }

    protected function boolFilterCanHasAssociates(&$result)
    {
        $result['whereClause'][] = [
            'canHasAssociates' => true
        ];
    }

    protected function boolFilterOnlyForDerivativeEnabled(&$result)
    {
        $result['whereClause'][] = [
            'onlyForDerivativeEnabled' => true
        ];
    }

    protected function boolFilterNotContributorOrChangeRequestDerivative(&$result)
    {
        $result['whereClause'][] = [
            'notContributorOrChangeRequestDerivative' => true
        ];
    }

    protected function boolFilterOnlyBaseAndHierarchyTypes(&$result)
    {
        $result['whereClause'][] = [
            'onlyBaseAndHierarchyTypes' => true
        ];
    }

    protected function boolFilterNotPrimaryOfContributorDerivative(&$result)
    {
        $result['whereClause'][] = [
            'notPrimaryOfContributorDerivative' => true
        ];
    }
}
