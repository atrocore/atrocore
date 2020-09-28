<?php

namespace Espo\Controllers;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\BadRequest;

class DataPrivacy extends \Espo\Core\Controllers\Base
{
    protected function checkControllerAccess()
    {
        if ($this->getAcl()->get('dataPrivacyPermission') === 'no') {
            throw new Forbidden();
        }
    }

    public function postActionErase($params, $data)
    {
        if (empty($data->entityType) || empty($data->id) || empty($data->fieldList) || !is_array($data->fieldList)) {
            throw new BadRequest();
        }

        return $this->getServiceFactory()->create('DataPrivacy')->erase($data->entityType, $data->id, $data->fieldList);
    }

}
