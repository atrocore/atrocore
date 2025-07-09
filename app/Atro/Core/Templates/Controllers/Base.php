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

namespace Atro\Core\Templates\Controllers;

use Atro\Controllers\AbstractRecordController;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Slim\Http\Request;

class Base extends AbstractRecordController
{
    public function actionTree($params, $data, $request): array
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }


        $params = [
            'select' =>   ['id', 'name'],
            'where'       => $this->prepareWhereQuery($request->get('where')),
            'asc'         => $request->get('asc', 'true') === 'true',
            'sortBy'      => $request->get('sortBy'),
            'offset'      => (int)$request->get('offset'),
            'maxSize'     => empty($request->get('maxSize')) ? $this->getConfig()->get('recordsPerPageSmall', 20) : (int)$request->get('maxSize')
        ];

        $data = $this->getRecordService()->findEntities($params);
        $result = [];
        foreach ($data['collection'] as $key => $entity) {
            $result[] = [
                'id' => $entity->id,
                'name' => $entity->get('name'),
                'load_on_demand' => false,
                'offset' => ($request->get('offset')  ?? 0) + $key,
                'total' => $data['total']
            ];
        }

        return [
            'list' => $result,
            'total' => $data['total']
        ];
    }
}
