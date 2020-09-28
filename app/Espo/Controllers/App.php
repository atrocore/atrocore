<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\BadRequest;

class App extends \Espo\Core\Controllers\Base
{
    public function actionUser()
    {
        return $this->getServiceFactory()->create('App')->getUserData();
    }

    public function postActionDestroyAuthToken($params, $data)
    {
        if (empty($data->token)) {
            throw new BadRequest();
        }

        $auth = new \Espo\Core\Utils\Auth($this->getContainer());
        return $auth->destroyAuthToken($data->token);
    }
}
