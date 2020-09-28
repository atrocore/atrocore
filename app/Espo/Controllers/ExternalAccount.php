<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;

class ExternalAccount extends \Espo\Core\Controllers\Record
{
    public static $defaultAction = 'list';

    protected function checkControllerAccess()
    {
        if (!$this->getAcl()->checkScope('ExternalAccount')) {
            throw new Forbidden();
        }
    }

    public function actionList($params, $data, $request)
    {
        $integrations = $this->getEntityManager()->getRepository('Integration')->find();
        $arr = array();
        foreach ($integrations as $entity) {
            if ($entity->get('enabled') && $this->getMetadata()->get('integrations.' . $entity->id .'.allowUserAccounts')) {
                $arr[] = array(
                    'id' => $entity->id
                );
            }
        }
        return array(
            'list' => $arr
        );
    }

    public function actionGetOAuth2Info($params, $data, $request)
    {
        $id = $request->get('id');
        list($integration, $userId) = explode('__', $id);

        if ($this->getUser()->id != $userId) {
            throw new Forbidden();
        }

        $entity = $this->getEntityManager()->getEntity('Integration', $integration);
        if ($entity) {
            return array(
                'clientId' => $entity->get('clientId'),
                'redirectUri' => $this->getConfig()->get('siteUrl') . '?entryPoint=oauthCallback',
                'isConnected' => $this->getRecordService()->ping($integration, $userId)
            );
        }
    }

    public function actionRead($params, $data, $request)
    {
        list($integration, $userId) = explode('__', $params['id']);

        if ($this->getUser()->id != $userId) {
            throw new Forbidden();
        }

        $entity = $this->getEntityManager()->getEntity('ExternalAccount', $params['id']);
        return $entity->toArray();
    }

    public function actionUpdate($params, $data, $request)
    {
        return $this->actionPatch($params, $data, $request);
    }

    public function actionPatch($params, $data, $request)
    {
        if (!$request->isPut() && !$request->isPost() && !$request->isPatch()) {
            throw new BadRequest();
        }

        list($integration, $userId) = explode('__', $params['id']);

        if ($this->getUser()->id != $userId) {
            throw new Forbidden();
        }

        if (isset($data->enabled) && !$data->enabled) {
            $data->data = null;
        }

        $entity = $this->getEntityManager()->getEntity('ExternalAccount', $params['id']);
        $entity->set($data);
        $this->getEntityManager()->saveEntity($entity);

        return $entity->toArray();
    }

    public function actionAuthorizationCode($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new Error('Bad HTTP method type.');
        }

        $id = $data->id;
        $code = $data->code;

        list($integration, $userId) = explode('__', $id);

        if ($this->getUser()->id != $userId) {
            throw new Forbidden();
        }

        $service = $this->getRecordService();
        return $service->authorizationCode($integration, $userId, $code);
    }
}
