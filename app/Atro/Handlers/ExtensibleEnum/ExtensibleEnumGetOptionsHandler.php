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
    path: '/ExtensibleEnum/{id}/options',
    methods: [
        'GET',
    ],
    summary: 'Get extensible enum options',
    description: 'Returns the full list of options for the specified extensible enum.',
    tag: 'ExtensibleEnum',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the extensible enum whose options should be returned',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'List of options belonging to the extensible enum',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            '$ref' => '#/components/schemas/ExtensibleEnumOption',
                        ],
                    ],
                ],
            ],
        ],
        404 => [
            'description' => 'Not found — no extensible enum with the given ID exists',
        ],
    ],
    entities: [
        'ExtensibleEnumOption',
    ],
)]
class ExtensibleEnumGetOptionsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');

        return new JsonResponse($this->getRecordService('ExtensibleEnum')->getExtensibleEnumOptions($id));
    }
}
