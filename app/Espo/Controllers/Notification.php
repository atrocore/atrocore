<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Error;

class Notification extends \Espo\Core\Controllers\Record
{
    public static $defaultAction = 'list';

    public function actionList($params, $data, $request)
    {
        $userId = $this->getUser()->id;

        $offset = intval($request->get('offset'));
        $maxSize = intval($request->get('maxSize'));
        $after = $request->get('after');

        if (empty($maxSize)) {
            $maxSize = self::MAX_SIZE_LIMIT;
        }

        $params = array(
            'offset' => $offset,
            'maxSize' => $maxSize,
            'after' => $after
        );

        $result = $this->getService('Notification')->getList($userId, $params);

        return array(
            'total' => $result['total'],
            'list' => $result['collection']->toArray()
        );
    }

    public function actionNotReadCount()
    {
        $userId = $this->getUser()->id;
        return $this->getService('Notification')->getNotReadCount($userId);
    }

    public function postActionMarkAllRead($params, $data, $request)
    {
        $userId = $this->getUser()->id;
        return $this->getService('Notification')->markAllRead($userId);
    }

    public function actionExport($params, $data, $request)
    {
        throw new Error();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        throw new Error();
    }

    public function actionCreateLink($params, $data, $request)
    {
        throw new Error();
    }

    public function actionRemoveLink($params, $data, $request)
    {
        throw new Error();
    }

    public function actionMerge($params, $data, $request)
    {
        throw new Error();
    }
}

