<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Forbidden;

class ActionHistoryRecord extends \Espo\Core\Controllers\Record
{
    public function actionUpdate($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionCreate($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionListLinked($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionCreateLink($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionRemoveLink($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMassDelete($params, $data, $request)
    {
        throw new Forbidden();
    }
}

