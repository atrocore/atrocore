<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;

class Attachment extends \Espo\Core\Controllers\Record
{
    public function actionList($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }
        return parent::actionList($params, $data, $request);
    }
}
