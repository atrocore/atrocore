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
    path: '/ClusterItem/unmerge',
    methods: [
        'POST',
    ],
    summary: 'Unmerge cluster item(s)',
    description: 'Unmerge one or multiple cluster items into a new cluster. Accepts a single id, a list of ids, or a query where clause.',
    tag: 'ClusterItem',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id'     => [
                            'type' => 'string',
                        ],
                        'idList' => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        'where'  => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'object',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Success',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
    ],
)]
class ClusterItemUnmergeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $data          = $this->getRequestBody($request);
        $recordService = $this->getRecordService('ClusterItem');
        $params        = [];

        if (property_exists($data, 'where')) {
            $params['where'] = json_decode(json_encode($data->where), true);
        }

        if (property_exists($data, 'idList')) {
            $params['ids'] = $data->idList;
        }

        if (property_exists($data, 'id')) {
            $params['ids'] = [$data->id];
        }

        if (empty($params)) {
            throw new BadRequest($this->getLanguage()->translate('idOrIdListOrWhereRequired', 'exceptions', 'ClusterItem'));
        }

        return new JsonResponse($recordService->unmerge($params));
    }
}
