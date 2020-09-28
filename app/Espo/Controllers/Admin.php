<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;

class Admin extends \Espo\Core\Controllers\Base
{
    protected function checkControllerAccess()
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }
    }

    public function postActionClearCache($params)
    {
        $result = $this->getContainer()->get('dataManager')->clearCache();
        return $result;
    }

    public function actionJobs()
    {
        $scheduledJob = $this->getContainer()->get('scheduledJob');

        return $scheduledJob->getAvailableList();
    }

    public function actionCronMessage($params)
    {
        return $this->getContainer()->get('scheduledJob')->getSetupMessage();
    }
}
