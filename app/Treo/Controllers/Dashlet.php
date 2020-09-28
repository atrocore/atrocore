<?php

declare(strict_types=1);

namespace Treo\Controllers;

use Espo\Core\Exceptions;
use Treo\Services\DashletInterface;
use Slim\Http\Request;
use Espo\Core\Controllers\Base;

/**
 * Class DashletController
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Dashlet extends Base
{

    /**
     * Get dashlet
     *
     * @ApiDescription(description="Get Dashlet data")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/Dashlet/{dashletName}")
     * @ApiParams(name="dashletName", type="string", is_required=1, description="Dashlet name")
     * @ApiReturn(sample="[{
     *     'total': 'integer',
     *     'list': 'array'
     * }]")
     *
     * @param         $params
     * @param         $data
     * @param Request $request
     *
     * @return array
     * @throws Exceptions\Error
     * @throws Exceptions\BadRequest
     */
    public function actionGetDashlet($params, $data, Request $request): array
    {
        // is get?
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        if (!empty($params['dashletName'])) {
            return $this->createDashletService($params['dashletName'])->getDashlet();
        }

        throw new Exceptions\Error();
    }

    /**
     * Create dashlet service
     *
     * @param string $dashletName
     *
     * @return DashletInterface
     * @throws Exceptions\Error
     */
    protected function createDashletService(string $dashletName): DashletInterface
    {
        $serviceName = ucfirst($dashletName) . 'Dashlet';

        $dashletService = $this->getService($serviceName);

        if (!$dashletService instanceof DashletInterface) {
            $message = sprintf($this->translate('notDashletService'), $serviceName);

            throw new Exceptions\Error($message);
        }

        return $dashletService;
    }

    /**
     * Translate
     *
     * @param string $key
     *
     * @param string $category
     *
     * @return string
     */
    protected function translate(string $key, string $category = 'exceptions'): string
    {
        return $this->getContainer()->get('language')->translate($key, $category);
    }
}
