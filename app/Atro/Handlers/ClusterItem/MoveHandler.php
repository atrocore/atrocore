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

declare(strict_types=1);

namespace Atro\Handlers\ClusterItem;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ClusterItem/action/move',
    methods: ['POST'],
    summary: 'Move cluster item(s) to another cluster',
    description: 'Move one or multiple cluster items to a target cluster. Accepts a single id, a list of ids, or a query where clause. Items rejected in the target cluster will be skipped.',
    tag: 'ClusterItem',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['targetClusterId'], 'properties' => ['id' => ['type' => 'string'], 'idList' => ['type' => 'array', 'items' => ['type' => 'string']], 'where' => ['type' => 'array', 'items' => ['type' => 'object']], 'targetClusterId' => ['type' => 'string']]]]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
class MoveHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $data          = $this->getRequestBody($request);
        $recordService = $this->getRecordService('ClusterItem');
        $params        = [];

        $targetClusterId = null;
        if (property_exists($data, 'targetClusterId') && !empty($data->targetClusterId)) {
            $targetClusterId = (string)$data->targetClusterId;
        } elseif (property_exists($data, 'selectedRecords') && !empty($data->selectedRecords[0]->entityId)) {
            $targetClusterId = (string)$data->selectedRecords[0]->entityId;
        }

        if (empty($targetClusterId)) {
            throw new BadRequest($this->getLanguage()->translate('targetClusterIdRequired', 'exceptions', 'ClusterItem'));
        }

        $params['targetClusterId'] = $targetClusterId;

        if (property_exists($data, 'where')) {
            $params['where'] = json_decode(json_encode($data->where), true);
        }

        if (property_exists($data, 'idList')) {
            $params['ids'] = $data->idList;
        }

        if (property_exists($data, 'id')) {
            $params['ids'] = [$data->id];
        }

        if (empty($params['where']) && empty($params['ids'])) {
            throw new BadRequest($this->getLanguage()->translate('idOrIdListOrWhereRequired', 'exceptions', 'ClusterItem'));
        }

        return new JsonResponse($recordService->move($params));
    }
}
