<?php
declare(strict_types=1);

namespace Treo\Controllers;

use Espo\Core\Controllers\Base;
use Espo\Core\Exceptions;
use Espo\Core\Utils\Json;
use Slim\Http\Request;
use Treo\Services\Composer as ComposerService;

/**
 * Composer controller
 *
 * @author r.ratsun@treolabs.com
 */
class Composer extends Base
{
    /**
     * @ApiDescription(description="Call composer update command")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/Composer/runUpdate")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
    public function actionRunUpdate($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        if (file_exists(ComposerService::CHECK_UP_FILE)) {
            throw new Exceptions\BadRequest('Composer daemon is not running');
        }

        return $this->getComposerService()->runUpdate();
    }

    /**
     * @ApiDescription(description="Cancel changes")
     * @ApiMethod(type="DELETE")
     * @ApiRoute(name="/Composer/cancelUpdate")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
    public function actionCancelUpdate($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isDelete()) {
            throw new Exceptions\BadRequest();
        }

        // cancel changes
        $this->getComposerService()->cancelChanges();

        return true;
    }

    /**
     * @ApiDescription(description="Get modules")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/ModuleManager/list")
     * @ApiReturn(sample="{
     *     'total': 1,
     *     'list': [
     *           {
     *               'id': 'Revisions',
     *               'name': 'Revisions',
     *               'version': '1.0.0',
     *               'description': 'Module Revisions for TreoPIM.',
     *               'required': [],
     *               'isActive': true
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

        return $this->getComposerService()->getList();
    }

    /**
     * @ApiDescription(description="Install module")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/ModuleManager/installModule")
     * @ApiBody(sample="{
     *     'id': 'Erp',
     *     'version': '1.0.0' - not required
     * }")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
    public function actionInstallModule($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        // prepare data
        $data = Json::decode(Json::encode($data), true);

        if (!empty($data['id'])) {
            // prepare version
            $version = (!empty($data['version'])) ? $data['version'] : null;

            return $this->getComposerService()->installModule($data['id'], $version);
        }

        throw new Exceptions\NotFound();
    }

    /**
     * @ApiDescription(description="Update module version")
     * @ApiMethod(type="PUT")
     * @ApiRoute(name="/ModuleManager/updateModule")
     * @ApiBody(sample="{
     *     'id': 'Erp',
     *     'version': '1.1.0'
     * }")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
    public function actionUpdateModule($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPut()) {
            throw new Exceptions\BadRequest();
        }

        // prepare data
        $data = Json::decode(Json::encode($data), true);

        if (!empty($data['id']) && !empty($data['version'])) {
            return $this->getComposerService()->updateModule($data['id'], $data['version']);
        }

        throw new Exceptions\NotFound();
    }

    /**
     * @ApiDescription(description="Delete module")
     * @ApiMethod(type="DELETE")
     * @ApiRoute(name="/ModuleManager/deleteModule")
     * @ApiBody(sample="{
     *     'id': 'Erp'
     * }")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
    public function actionDeleteModule($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isDelete()) {
            throw new Exceptions\BadRequest();
        }

        // prepare data
        $data = Json::decode(Json::encode($data), true);

        if (!empty($id = $data['id'])) {
            return $this->getComposerService()->deleteModule($id);
        }

        throw new Exceptions\NotFound();
    }

    /**
     * @ApiDescription(description="Cancel module changes")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/ModuleManager/cancel")
     * @ApiBody(sample="{
     *     'id': 'Erp'
     * }")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
    public function actionCancel($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        // prepare data
        $data = Json::decode(Json::encode($data), true);

        if (!empty($id = $data['id'])) {
            return $this->getComposerService()->cancel($id);
        }

        throw new Exceptions\NotFound();
    }

    /**
     * @ApiDescription(description="Get composer stream data")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/ModuleManager/logs")
     * @ApiReturn(sample="{
     *     'total': 'int',
     *     'list': 'array'
     * }")
     *
     * @return array
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     */
    public function actionLogs($params, $data, Request $request): array
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        return $this->getComposerService()->getLogs($request);
    }


    /**
     * @param mixed   $params
     * @param mixed   $data
     * @param Request $request
     *
     * @return array
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionCheck($params, $data, Request $request): array
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        return $this->getComposerService()->checkUpdate();
    }

    /**
     * @return ComposerService
     */
    protected function getComposerService(): ComposerService
    {
        return $this->getService('Composer');
    }
}
