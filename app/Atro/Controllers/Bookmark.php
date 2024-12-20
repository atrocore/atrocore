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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Controllers\Base;

class Bookmark extends Base
{
    public function actionTree($params, $data, $request)
    {
        if (!$request->isGet() || empty($request->get('scope'))) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        if (!$this->getAcl()->check($request->get('scope'), 'read')) {
            throw new Forbidden();
        }

        $params = [
            'where'       => $this->prepareWhereQuery($request->get('where')),
            'asc'         => $request->get('asc', 'true') === 'true',
            'sortBy'      => $request->get('sortBy', 'name'),
            'offset'      => (int)$request->get('offset'),
            'maxSize'     => empty($request->get('maxSize')) ? $this->getConfig()->get('recordsPerPageSmall', 20) : (int)$request->get('maxSize')
        ];

        return $this->getRecordService()->getBookmarkTree($request->get('scope'), $params);

    }

    public function actionTreeData($params, $data, $request)
    {
         $result = $this->actionTree($params, $data, $request);
         return [
             'total' => $result['total'],
             'tree' => $result['list']
         ];
    }
    public function actionUpdate($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionPatch($params, $data, $request)
    {
        throw  new Forbidden();
    }
}
