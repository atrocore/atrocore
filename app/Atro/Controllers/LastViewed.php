<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Controllers;

use Atro\Core\Exceptions\BadRequest;

class LastViewed extends \Espo\Core\Controllers\Base
{
    public function actionGetNavigationHistory($params, $data, $request)
    {
        $entityName = $request->get('entity');
        $entityId = $request->get('id');
        $tabId = $request->get('tabId');
        $maxSize = intval($request->get('maxSize')) ?: 3;

        return $this->getLastViewedService()->getLastEntities($maxSize, $entityName, $entityId, $tabId);
    }

    public function getActionIndex($params, $data, $request)
    {
        $offset = intval($request->get('offset'));
        $maxSize = intval($request->get('maxSize'));

        $params = array(
            'offset'  => $offset,
            'maxSize' => $maxSize
        );

        $result = $this->getLastViewedService()->get($params);

        return [
            'total' => $result['total'],
            'list'  => isset($result['collection']) ? $result['collection']->toArray() : $result['list']
        ];
    }

    public function actionTree($params, $data, $request): array
    {
        if (!$request->isGet() || empty($request->get('scope'))) {
            throw new BadRequest();
        }

        return $this->getLastViewedService()->getLastVisitItemsTreeData($request->get('scope'), (int)$request->get('offset'));
    }

    public function getLastViewedService(): \Atro\Services\LastViewed
    {
        return $this->getServiceFactory()->create('LastViewed');
    }
}

