<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Forbidden;

class Metadata extends \Espo\Core\Controllers\Base
{

    public function actionRead($params, $data)
    {
        return $this->getMetadata()->getAllForFrontend();
    }

    public function getActionGet($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new \Forbidden();
        }
        $key = $request->get('key');

        return $this->getMetadata()->get($key, false);
    }
}
