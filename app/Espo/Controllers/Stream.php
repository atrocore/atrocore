<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Error;

class Stream extends \Espo\Core\Controllers\Base
{
    const MAX_SIZE_LIMIT = 200;

    public static $defaultAction = 'list';

    public function actionList($params, $data, $request)
    {
        $scope = $params['scope'];
        $id = isset($params['id']) ? $params['id'] : null;

        $offset = intval($request->get('offset'));
        $maxSize = intval($request->get('maxSize'));
        $after = $request->get('after');
        $filter = $request->get('filter');

        $service = $this->getService('Stream');

        if (empty($maxSize)) {
            $maxSize = self::MAX_SIZE_LIMIT;
        }
        if (!empty($maxSize) && $maxSize > self::MAX_SIZE_LIMIT) {
            throw new Forbidden();
        }

        $result = $service->find($scope, $id, array(
            'offset' => $offset,
            'maxSize' => $maxSize,
            'after' => $after,
            'filter' => $filter
        ));

        return array(
            'total' => $result['total'],
            'list' => $result['collection']->toArray()
        );
    }
}

