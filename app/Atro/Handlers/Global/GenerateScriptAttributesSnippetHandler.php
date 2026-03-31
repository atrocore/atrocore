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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/generateScriptAttributesSnippet',
    methods: [
        'POST',
    ],
    summary: 'Generate script attributes snippet',
    description: 'Accepts a list of attribute IDs for the specified entity and returns a JSON snippet '
        . 'with the resolved attribute field keys mapped to null, plus a reserved "__attributes" key '
        . 'containing the original attribute IDs. '
        . 'Intended for use in the script field editor: the returned text is inserted directly into the Monaco editor '
        . 'as a ready-to-use JSON object template for working with entity attributes.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['entityName', 'attributesIds'],
                    'properties' => [
                        'entityName'    => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. "Product").',
                            'example'     => 'Product',
                        ],
                        'attributesIds' => [
                            'type'        => 'array',
                            'description' => 'List of attribute IDs to include in the snippet.',
                            'items'       => ['type' => 'string'],
                            'example'     => ['abc123', 'def456'],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'JSON snippet ready to be inserted into the script editor.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'text' => [
                                'type'        => 'string',
                                'description' => 'JSON-encoded object with resolved attribute field keys mapped to null '
                                    . 'and a "__attributes" key containing the original attribute IDs '
                                    . '(e.g. "{\"color\":null,\"size\":null,\"__attributes\":[\"abc123\",\"def456\"]}"). '
                                    . 'Empty string if no attribute fields were resolved.',
                                'example'     => '{"color":null,"size":null,"__attributes":["abc123","def456"]}',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName or attributesIds is missing / attributesIds is not an array.',
        ],
    ],
)]
class GenerateScriptAttributesSnippetHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityName) || empty($data->attributesIds) || !is_array($data->attributesIds)) {
            throw new BadRequest();
        }

        return new JsonResponse(
            $this->getServiceFactory()->create('App')->prepareScriptAttributes($data->entityName, $data->attributesIds)
        );
    }
}
