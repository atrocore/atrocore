<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\NotFound;

class LeadCapture extends \Espo\Core\Controllers\Record
{
    public function postActionLeadCapture($params, $data, $request, $response)
    {
        if (empty($params['apiKey'])) throw new BadRequest('No API key provided.');
        if (empty($data)) throw new BadRequest('No payload provided.');

        $allowOrigin = $this->getConfig()->get('leadCaptureAllowOrigin', '*');
        $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);

        return $this->getRecordService()->leadCapture($params['apiKey'], $data);
    }

    public function optionsActionLeadCapture($params, $data, $request, $response)
    {
        if (empty($params['apiKey'])) throw new BadRequest('No API key provided.');

        if (!$this->getRecordService()->isApiKeyValid($params['apiKey'])) {
            throw new NotFound();
        }

        $allowOrigin = $this->getConfig()->get('leadCaptureAllowOrigin', '*');

        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
        $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'POST');

        return true;
    }

    public function postActionGenerateNewApiKey($params, $data, $request)
    {
        if (empty($data->id)) throw new BadRequest();

        return $this->getRecordService()->generateNewApiKeyForEntity($data->id)->getValueMap();
    }
}
