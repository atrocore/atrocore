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
    path: '/startEntityListening',
    methods: [
        'POST',
    ],
    summary: 'Start listening to entity record changes',
    description: 'Registers a real-time listening session for the specified entity record. '
        . 'Creates a public JSON file that gets updated whenever the record changes. '
        . 'Returns a timestamp and the path to that file so the client can poll it to detect changes. '
        . 'This endpoint is intended for internal use by the AtroCore UI only — external API consumers should not call it.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['entityName', 'entityId'],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. "Product").',
                            'example'     => 'Product',
                        ],
                        'entityId'   => [
                            'type'        => 'string',
                            'description' => 'ID of the entity record to listen to.',
                            'example'     => 'a01k1g09hhce8m8pkmzt3zzyq5v',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Listening session data.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'timestamp' => [
                                'type'        => 'integer',
                                'description' => 'Unix timestamp of when the listening session was created or last established.',
                                'example'     => 1743379200,
                            ],
                            'endpoint'  => [
                                'type'        => 'string',
                                'description' => 'Relative public path to the JSON file the client should poll to detect record changes.',
                                'example'     => 'listening/entity/Product/a01k1g09hhce8m8pkmzt3zzyq5v.json',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName or entityId is missing.',
        ],
    ],
)]
class StartEntityListeningHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        return new JsonResponse(
            $this->container->get('realtimeManager')->startEntityListening($data->entityName, $data->entityId)
        );
    }
}
