<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Forbidden;

class AuthToken extends \Espo\Core\Controllers\Record
{
    protected function checkControllerAccess()
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }
    }

    public function actionUpdate($params, $data, $request)
    {
        $dataAr = get_object_vars($data);

        if (
            is_object($data)
            &&
            isset($data->isActive)
            &&
            $data->isActive === false
            &&
            count(array_keys($dataAr)) === 1
        ) {
            return parent::actionUpdate($params, $data, $request);
        }
        throw new Forbidden();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        if (empty($data->attributes)) {
            throw new BadRequest();
        }

        $attributes = $data->attributes;

        if (
            is_object($attributes)
            &&
            isset($attributes->isActive)
            &&
            $attributes->isActive === false
            &&
            count(array_keys(get_object_vars($attributes))) === 1
        ) {
            return parent::actionMassUpdate($params, $data, $request);
        }
        throw new Forbidden();
    }

    public function actionCreate($params, $data, $request)
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
}
