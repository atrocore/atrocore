<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\Error;

class Pdf extends \Espo\Core\Controllers\Base
{
    public function postActionMassPrint($params, $data)
    {
        if (empty($data->idList) || !is_array($data->idList)) {
            throw new BadRequest();
        }
        if (empty($data->entityType)) {
            throw new BadRequest();
        }
        if (empty($data->templateId)) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->checkScope('Template')) {
            throw new Forbidden();
        }
        if (!$this->getAcl()->checkScope($data->entityType)) {
            throw new Forbidden();
        }

        return [
            'id' => $this->getServiceFactory()->create('Pdf')->massGenerate($data->entityType, $data->idList, $data->templateId, true)
        ];
    }
}
