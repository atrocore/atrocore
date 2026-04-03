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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExtensibleEnum/action/getExtensibleEnumsOptions',
    methods: [
        'GET',
    ],
    summary: 'Get extensible enums options for multiple lists',
    description: 'Returns the options for multiple extensible enums.',
    tag: 'ExtensibleEnum',
    parameters: [
        [
            'name'     => 'extensibleEnumIds',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
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
            'description' => 'Options mapped by enum ID',
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
    ],
    entities: [
        'ExtensibleEnumOption',
    ],
)]
class ExtensibleEnumGetOptionsMultipleHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp  = $request->getQueryParams();
        $ids = $qp['extensibleEnumIds'] ?? null;

        if (is_string($ids)) {
            $ids = @json_decode($ids, true);
        }

        if (empty($ids)) {
            throw new BadRequest();
        }

        return new JsonResponse($this->getRecordService('ExtensibleEnum')->getExtensibleEnumsOptions($ids));
    }
}
