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
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;

class File extends Base
{
    public function actionCreate($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->createEntity($data);
    }

    public function actionTree($params, $data, $request): array
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        if (empty($request->get('node')) && !empty($request->get('selectedId'))) {
            return $this->getRecordService()->getTreeDataForSelectedNode((string)$request->get('selectedId'));
        }

        $params = [
            'where'       => $this->prepareWhereQuery($request->get('where')),
            'asc'         => $request->get('asc', 'true') === 'true',
            'sortBy'      => $request->get('sortBy'),
            'isTreePanel' => !empty($request->get('isTreePanel')),
            'offset'      => (int)$request->get('offset'),
            'maxSize'     => empty($request->get('maxSize')) ? $this->getConfig()->get('recordsPerPageSmall', 20) : (int)$request->get('maxSize')
        ];

        return $this->getRecordService()->getChildren((string)$request->get('node'), $params);
    }
}
