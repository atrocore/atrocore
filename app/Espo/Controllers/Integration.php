<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;

class Integration extends \Espo\Core\Controllers\Record
{
    protected function checkControllerAccess()
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }
    }

    public function actionIndex($params, $data, $request)
    {
        return false;
    }

    public function actionRead($params, $data, $request)
    {
        $entity = $this->getEntityManager()->getEntity('Integration', $params['id']);
        return $entity->toArray();
    }

    public function actionUpdate($params, $data, $request)
    {
        return $this->actionPatch($params, $data, $request);
    }

    public function actionPatch($params, $data, $request)
    {
        if (!$request->isPut() && !$request->isPatch()) {
            throw new BadRequest();
        }
        $entity = $this->getEntityManager()->getEntity('Integration', $params['id']);
        $entity->set($data);
        $this->getEntityManager()->saveEntity($entity);

        $integrationsConfigData = $this->getConfig()->get('integrations');

        if (!$integrationsConfigData || !($integrationsConfigData instanceof \StdClass)) {
            $integrationsConfigData = (object)[];
        }
        $integrationName = $params['id'];

        $integrationsConfigData->$integrationName = $entity->get('enabled');
        $this->getConfig()->set('integrations', $integrationsConfigData);

        $this->getConfig()->save();

        return $entity->toArray();
    }
}

