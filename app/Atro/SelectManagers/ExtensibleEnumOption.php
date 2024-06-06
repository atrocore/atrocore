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

use Atro\Core\Exceptions\BadRequest;
use Espo\Core\SelectManagers\Base;

class ExtensibleEnumOption extends Base
{
    protected function boolFilterDefaultOption(array &$result): void
    {
        $data = $this->getBoolFilterParameter('defaultOption');
        if (empty($data['extensibleEnumId'])) {
            throw new BadRequest('For choosing default option, you need to select List.');
        }

        $this->addExtensibleEnumIdWhere($data['extensibleEnumId'], $result);
    }

    protected function boolFilterOnlyForExtensibleEnum(array &$result): void
    {
        $this->addExtensibleEnumIdWhere($this->getBoolFilterParameter('onlyForExtensibleEnum'), $result);
    }

    private function addExtensibleEnumIdWhere($extensibleEnumId, &$result){
        $where =[[
            "type" => "linkedWith",
            "attribute" => "extensibleEnums",
            "value" => [$extensibleEnumId]
        ]] ;

        $this->prepareRelationshipFilterField($where);

        $result['whereClause'][] = $this->convertWhere($where,false,$result);
    }
}
