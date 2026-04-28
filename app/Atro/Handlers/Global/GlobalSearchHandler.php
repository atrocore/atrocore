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

namespace Atro\Handlers\Global;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/globalSearch',
    methods: [
        'GET',
    ],
    summary: 'Global search',
    description: 'Searches for records matching the query string across all entity types that have global search enabled. '
        . 'Results are limited to 20 records in total, ordered by entity type as configured. '
        . 'Only entity types accessible to the current user are included.',
    tag: 'Global',
    parameters: [
        [
            'name'        => 'q',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Search query string (minimum 2 characters).',
            'schema'      => [
                'type'    => 'string',
                'example' => 'laptop',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Matching records found across entity types.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'count' => [
                                'type'        => 'integer',
                                'description' => 'Total number of records returned (maximum 20).',
                                'example'     => 3,
                            ],
                            'list'  => [
                                'type'        => 'array',
                                'description' => 'Matched records. Each item includes at minimum `id`, `name`, and `_scope` (entity type name).',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'id'     => [
                                            'type'        => 'string',
                                            'description' => 'Record ID.',
                                        ],
                                        'name'   => [
                                            'type'        => 'string',
                                            'description' => 'Record name.',
                                        ],
                                        '_scope' => [
                                            'type'        => 'string',
                                            'description' => 'Entity name the record belongs to (e.g. "Product", "Category").',
                                            'example'     => 'Product',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class GlobalSearchHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        $result = $this->getServiceFactory()->create('GlobalSearch')->find(
            (string) ($qp['q'] ?? ''),
            (int)    ($qp['offset'] ?? 0),
            (int)    ($qp['maxSize'] ?? 0),
        );

        return new JsonResponse($result);
    }
}
