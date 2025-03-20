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

namespace Atro\Controllers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Controllers\Base;

class SavedSearch extends Base
{
    protected function fetchListParamsFromRequest(&$params, $request, $data)
    {
        if(empty($request->get('scope'))) {
            throw new BadRequest();
        }

        $params['where'][] = [
            "type" => "equals",
            "attribute" => "entityType",
            "value" => $request->get('scope')
        ];

        $params['_scope'] = $request->get('scope');

        parent::fetchListParamsFromRequest($params, $request, $data);
    }
}
