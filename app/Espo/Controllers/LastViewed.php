<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Forbidden;

class LastViewed extends \Espo\Core\Controllers\Base
{
    public function getActionIndex($params, $data, $request)
    {
        $result = $this->getServiceFactory()->create('LastViewed')->get();

        return [
            'total' => $result['total'],
            'list' => isset($result['collection']) ? $result['collection']->toArray() : $result['list']
        ];
    }
}

