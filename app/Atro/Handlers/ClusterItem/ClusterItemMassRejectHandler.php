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
    path: '/ClusterItem/massReject',
    methods: [
        'POST',
    ],
    summary: 'Reject cluster items (mass action)',
    description: 'Rejects one or more cluster items, removing them from the active cluster set. Accepts a list of IDs via idList or a filter via where.',
    tag: 'ClusterItem',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'idList' => [
                            'type'        => 'array',
                            'description' => 'List of ClusterItem IDs to reject.',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                        'where'  => [
                            'type'        => 'array',
                            'description' => 'Filter criteria selecting ClusterItems to reject.',
                            'items'       => [
                                'type' => 'object',
                            ],
                        ],
                    ],
                    'anyOf'      => [
                        ['required' => ['idList']],
                        ['required' => ['where']],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Reject result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'count'  => [
                                'type'        => 'integer',
                                'description' => 'Number of cluster items successfully rejected.',
                            ],
                            'sync'   => [
                                'type'        => 'boolean',
                                'description' => 'true if executed synchronously, false if dispatched as a background job.',
                            ],
                            'errors' => [
                                'type'        => 'array',
                                'items'       => [
                                    'type' => 'string',
                                ],
                                'description' => 'List of error messages, if any.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Neither idList nor where was provided.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on ClusterItem.',
        ],
    ],
)]
class ClusterItemMassRejectHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $data   = $this->getRequestBody($request);
        $params = [];

        if (property_exists($data, 'where')) {
            $params['where'] = json_decode(json_encode($data->where), true);
        }

        if (property_exists($data, 'idList')) {
            $params['ids'] = $data->idList;
        }

        if (empty($params['ids']) && empty($params['where'])) {
            throw new BadRequest($this->getLanguage()->translate('idOrIdListOrWhereRequired', 'exceptions', 'ClusterItem'));
        }

        return new JsonResponse($this->getRecordService('ClusterItem')->reject($params));
    }
}
