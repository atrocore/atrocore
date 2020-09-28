<?php

namespace Espo\Controllers;

use Espo\Core\Utils as Utils;
use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;

class LabelManager extends \Espo\Core\Controllers\Base
{
    protected function checkControllerAccess()
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }
    }

    public function postActionGetScopeList($params)
    {
        $labelManager = $this->getContainer()->get('injectableFactory')->createByClassName('\\Espo\\Core\\Utils\\LabelManager');

        return $labelManager->getScopeList();
    }

    public function postActionGetScopeData($params, $data, $request)
    {
        if (empty($data->scope) || empty($data->language)) {
            throw new BadRequest();
        }
        $labelManager = $this->getContainer()->get('injectableFactory')->createByClassName('\\Espo\\Core\\Utils\\LabelManager');
        return $labelManager->getScopeData($data->language, $data->scope);
    }

    public function postActionSaveLabels($params, $data)
    {
        if (empty($data->scope) || empty($data->language) || !isset($data->labels)) {
            throw new BadRequest();
        }

        $labels = get_object_vars($data->labels);

        $labelManager = $this->getContainer()->get('injectableFactory')->createByClassName('\\Espo\\Core\\Utils\\LabelManager');
        $returnData = $labelManager->saveLabels($data->language, $data->scope, $labels);

        $this->getContainer()->get('dataManager')->clearCache();

        return $returnData;
    }
}
