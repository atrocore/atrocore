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
    path: '/generateScriptFieldsSnippet',
    methods: [
        'POST',
    ],
    summary: 'Generate script fields snippet',
    description: 'Accepts a list of field codes for the specified entity and returns a JSON snippet '
        . 'with those fields as keys and null as values. '
        . 'Intended for use in the script field editor: the returned text is inserted directly into the Monaco editor '
        . 'as a ready-to-use JSON object template. '
        . 'Only storable fields are included; virtual/computed fields (notStorable) are skipped. '
        . 'Link-multiple ID list fields are also included despite being notStorable.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['entityName', 'fields'],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. "Product", "Category").',
                            'example'     => 'Product',
                        ],
                        'fields'     => [
                            'type'        => 'array',
                            'description' => 'List of field codes to include in the snippet (e.g. ["name", "sku", "price"]).',
                            'items'       => ['type' => 'string'],
                            'example'     => ['name', 'sku', 'price'],
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
                                'description' => 'JSON-encoded object with resolved field keys mapped to null '
                                    . '(e.g. "{\"name\":null,\"sku\":null}"). '
                                    . 'Empty string if none of the requested fields exist or are storable.',
                                'example'     => '{"name":null,"sku":null,"price":null}',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName or fields is missing / fields is not an array.',
        ],
    ],
)]
class GenerateScriptFieldsSnippetHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityName) || empty($data->fields) || !is_array($data->fields)) {
            throw new BadRequest();
        }

        return new JsonResponse(
            $this->getServiceFactory()->create('App')->prepareScriptFields($data->entityName, $data->fields)
        );
    }
}
