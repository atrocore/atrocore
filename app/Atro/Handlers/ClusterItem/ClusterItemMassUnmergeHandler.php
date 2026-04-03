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
    path: '/ClusterItem/massUnmerge',
    methods: [
        'POST',
    ],
    summary: 'Unmerge cluster items (mass action)',
    description: 'Moves one or more cluster items out of their current cluster into a newly created cluster with the same masterEntity. All selected items must belong to the same source cluster and none may be the master entity item. Accepts a list of IDs via idList or a filter via where.',
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
                            'description' => 'List of ClusterItem IDs to unmerge.',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                        'where'  => [
                            'type'        => 'array',
                            'description' => 'Filter criteria selecting ClusterItems to unmerge.',
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
            'description' => 'Unmerge result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'count'  => [
                                'type'        => 'integer',
                                'description' => 'Number of cluster items successfully unmerged.',
                            ],
                            'sync'   => [
                                'type'        => 'boolean',
                                'description' => 'Always true — unmerge is executed synchronously.',
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
            'description' => 'Neither idList nor where was provided; items belong to different clusters; or a master entity item was selected.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on ClusterItem.',
        ],
    ],
)]
class ClusterItemMassUnmergeHandler extends AbstractHandler
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

        return new JsonResponse($this->getRecordService('ClusterItem')->unmerge($params));
    }
}
