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

namespace Atro\Handlers\ExtensibleEnum;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExtensibleEnum/options',
    methods: [
        'GET',
    ],
    summary: 'Get options for multiple extensible enums',
    description: 'Returns the options for multiple extensible enums in a single request, keyed by enum ID.',
    tag: 'ExtensibleEnum',
    parameters: [
        [
            'name'        => 'extensibleEnumIds',
            'in'          => 'query',
            'required'    => true,
            'description' => 'List of extensible enum IDs whose options should be returned',
            'schema'      => [
                'anyOf' => [
                    [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
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
            'description' => 'Options keyed by extensible enum ID',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'                 => 'object',
                        'additionalProperties' => [
                            'type'  => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/ExtensibleEnumOption',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Bad request — extensibleEnumIds is missing or empty',
        ],
    ],
    entities: [
        'ExtensibleEnumOption',
    ],
)]
class ExtensibleEnumGetOptionsMultipleHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ids = $request->getQueryParams()['extensibleEnumIds'] ?? null;

        if (is_string($ids)) {
            $ids = @json_decode($ids, true);
        }

        return new JsonResponse($this->getRecordService('ExtensibleEnum')->getExtensibleEnumsOptions((array) $ids));
    }
}
