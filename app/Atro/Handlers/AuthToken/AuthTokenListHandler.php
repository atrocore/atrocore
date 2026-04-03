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

namespace Atro\Handlers\AuthToken;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/AuthToken',
    methods: [
        'GET',
    ],
    summary: 'Returns a list of auth tokens',
    description: 'Returns a list of authentication tokens. Accessible by administrators only.',
    tag: 'AuthToken',
    parameters: [
        [
            'name'     => 'offset',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'integer',
                'example' => 0,
            ],
        ],
        [
            'name'     => 'maxSize',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'integer',
                'example' => 20,
            ],
        ],
        [
            'name'     => 'sortBy',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'asc',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'boolean',
            ],
        ],
        [
            'name'     => 'where',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'anyOf' => [
                    [
                        'type' => 'array',
                    ],
                    [
                        'type' => 'object',
                    ],
                    [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Collection of auth tokens',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total' => [
                                'type' => 'integer',
                            ],
                            'list'  => [
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
    ],
)]
class AuthTokenListHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $params = $this->buildListParams($request);
        $result = $this->getRecordService('AuthToken')->findEntities($params);

        return new JsonResponse($this->buildListResult($result, $params));
    }
}
