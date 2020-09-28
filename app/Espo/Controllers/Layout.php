<?php

namespace Espo\Controllers;

use Espo\Core\Utils as Utils;
use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;

class Layout extends \Espo\Core\Controllers\Base
{
    public function actionRead($params, $data)
    {
        $data = $this->getContainer()->get('layout')->get($params['scope'], $params['name']);
        if (empty($data)) {
            throw new NotFound("Layout " . $params['scope'] . ":" . $params['name'] . ' is not found.');
        }
        return $data;
    }

    public function actionUpdate($params, $data, $request)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        if (!$request->isPut() && !$request->isPatch()) {
            throw new BadRequest();
        }

        $layoutManager = $this->getContainer()->get('layout');
        $layoutManager->set($data, $params['scope'], $params['name']);
        $result = $layoutManager->save();

        if ($result === false) {
            throw new Error("Error while saving layout.");
        }

        $this->getContainer()->get('dataManager')->updateCacheTimestamp();

        return $layoutManager->get($params['scope'], $params['name']);
    }

    public function actionPatch($params, $data, $request)
    {
        return $this->actionUpdate($params, $data, $request);
    }

    public function actionResetToDefault($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }
        if (empty($data->scope) || empty($data->name)) {
            throw new BadRequest();
        }

        return $this->getContainer()->get('layout')->resetToDefault($data->scope, $data->name);
    }
}
