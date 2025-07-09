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

use Atro\Core\Templates\Controllers\Base;

class Measure extends Base
{
    public function actionMeasureWithUnits($params, $data, $request)
    {
        $params['id'] = $request->get('id');

        $res = $this->actionRead($params, $data, $request);
        $res->units = [];
        $measureUnits = $this->getRecordService()->findLinkedEntities($res->id, 'units', [
            'where'  => [
                [
                    'type'      => 'isTrue',
                    'attribute' => 'isActive'
                ]
            ],
            'sortBy' => 'createdAt',
            'asc'    => true
        ]);
        if (!empty($measureUnits['collection'][0])) {
            $res->units = $measureUnits['collection']->toArray();
        }

        return $res;
    }
}
