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

use Atro\Core\Exceptions;
use Espo\Core\Utils\Json;
use Atro\Services\Composer as ComposerService;
use Slim\Http\Request;

class Composer extends AbstractController
{
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

        if(empty($data['version'])) {
            throw new Exceptions\BadRequest();
        }

        if (!empty($data['id'])) {
            return $this->getComposerService()->updateModule($data['id'], $data['version']);
        }

        throw new Exceptions\NotFound();
    }

    public function actionDeleteModule($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isDelete()) {
            throw new Exceptions\BadRequest();
        }

        if (!empty($data)) {
            $data = Json::decode(Json::encode($data), true);
            if (!empty($data['id'])) {
                $id = $data['id'];
            }
        }

        if (!empty($request->get('id'))) {
            $id = $request->get('id');
        }

        if (!empty($id)) {
            return $this->getComposerService()->deleteModule($id);
        }

        throw new Exceptions\NotFound();
    }

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

    public function actionReleaseNotes($params, \stdClass $data, Request $request): array
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPost() || !property_exists($data, 'id') || empty($data->id)) {
            throw new Exceptions\BadRequest();
        }

        return ['html' => $this->getComposerService()->getReleaseNotes((string)$data->id)];
    }

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
