<?php

declare(strict_types=1);

namespace Multilang\Controllers;

use Espo\Core\Controllers\Base;
use Espo\Core\Exceptions\BadRequest;
use Treo\Core\Slim\Http\Request;

/**
 * Class Multilang
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Multilang extends Base
{
    /**
     * @ApiDescription(description="Update layouts")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/Multilang/action/updateLayouts")
     * @ApiReturn(sample="'bool'")
     *
     * @param array   $params
     * @param array   $data
     * @param Request $request
     *
     * @return bool
     * @throws BadRequest
     */
    public function actionUpdateLayouts($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        return $this->getService('Multilang')->updateLayouts();
    }
}
