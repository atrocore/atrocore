<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Error,
    \Espo\Core\Exceptions\Forbidden;

class GlobalSearch extends \Espo\Core\Controllers\Base
{
    public function actionSearch($params, $data, $request)
    {
        $query = $request->get('q');

        $offset = intval($request->get('offset'));
        $maxSize = intval($request->get('maxSize'));

        return $this->getService('GlobalSearch')->find($query, $offset, $maxSize);
    }
}

