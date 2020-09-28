<?php

declare(strict_types=1);

namespace Treo\Controllers;

use Espo\Core\Exceptions;
use Slim\Http\Request;

/**
 * Class Store
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class Store extends \Espo\Core\Controllers\Base
{
    /**
     * @ApiDescription(description="Get store list")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/Store/list")
     * @ApiReturn(sample="{
     *     'total': 1,
     *     'list': [
     *           {
     *               'id': 'Revisions',
     *               'name': 'Revisions',
     *               'version': '1.0.0',
     *               'description': 'Module Revisions.',
     *               'status': 'available'
     *           }
     *       ]
     * }")
     *
     * @return array
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     */
    public function actionList($params, $data, Request $request): array
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        return $this->getService('Store')->getList();
    }
}
