<?php

declare(strict_types=1);

namespace Treo\Controllers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Slim\Http\Request;
use Treo\Core\EventManager\Event;

/**
 * Class MassActions
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class MassActions extends \Espo\Core\Controllers\Base
{

    /**
     * @param array     $params
     * @param \stdClass $data
     * @param Request   $request
     *
     * @return array
     */
    public function actionMassUpdate(array $params, \stdClass $data, Request $request): array
    {
        if (!$request->isPut() || !isset($params['scope'])) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }
        if (empty($data->attributes)) {
            throw new BadRequest();
        }

        return $this->getService('MassActions')->massUpdate($params['scope'], $data);
    }

    /**
     * @param array     $params
     * @param \stdClass $data
     * @param Request   $request
     *
     * @return array
     * @throws BadRequest
     * @throws Forbidden
     */
    public function actionMassDelete(array $params, \stdClass $data, Request $request): array
    {
        if (!$request->isPost() || !isset($params['scope'])) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check($params['scope'], 'delete')) {
            throw new Forbidden();
        }

        $event = new Event(['params' => $params, 'data' => $data, 'request' => $request]);
        $this
            ->getContainer()
            ->get('eventManager')
            ->dispatch($params['scope'] . 'Controller', 'beforeActionMassDelete', $event);


        return $this->getService('MassActions')->massDelete($params['scope'], $data);
    }

    /**
     * Action add relation
     *
     * @param array     $params
     * @param \stdClass $data
     * @param Request   $request
     *
     * @return bool
     *
     * @throws BadRequest
     * @throws Forbidden
     */
    public function actionAddRelation(array $params, \stdClass $data, Request $request): bool
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }
        if (empty($data->ids) || empty($data->foreignIds) || !isset($params['scope']) || !isset($params['link'])) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }

        return $this
            ->getService('MassActions')
            ->addRelation($data->ids, $data->foreignIds, $params['scope'], $params['link']);
    }

    /**
     * Action remove relation
     *
     * @param array     $params
     * @param \stdClass $data
     * @param Request   $request
     *
     * @return bool
     *
     * @throws BadRequest
     * @throws Forbidden
     */
    public function actionRemoveRelation(array $params, \stdClass $data, Request $request): bool
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }
        if (empty($data->ids) || empty($data->foreignIds) || !isset($params['scope']) || !isset($params['link'])) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }

        return $this
            ->getService('MassActions')
            ->removeRelation($data->ids, $data->foreignIds, $params['scope'], $params['link']);
    }
}
