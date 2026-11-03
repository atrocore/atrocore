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

namespace Atro\Handlers\EntityFieldTypeScript;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/EntityFieldTypeScript/{id}/scriptPreview',
    methods: [
        'POST',
    ],
    summary: 'Preview the script of a script field',
    description: 'Renders the script of a script field on the first record of its entity. The script and the output type can be passed to preview changes that are not saved yet. Accessible by administrators only.',
    tag: 'EntityFieldTypeScript',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the script field, e.g. "Product_fullName"',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => false,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'script'     => [
                            'type'        => 'string',
                            'description' => 'The script to render, the script of the field by default',
                        ],
                        'outputType' => [
                            'type'        => 'string',
                            'description' => 'The output type, the output type of the field by default',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'The rendered preview',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'preview'    => [
                                'anyOf' => [
                                    ['type' => 'string', 'nullable' => true],
                                    ['type' => 'number'],
                                    ['type' => 'boolean'],
                                ],
                            ],
                            'entityName' => [
                                'type' => 'string',
                            ],
                            'entity'     => [
                                'type'     => 'object',
                                'nullable' => true,
                            ],
                            'outputType' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden — only administrators can preview scripts',
        ],
        404 => [
            'description' => 'Not found — no script field with the given ID exists',
        ],
    ],
)]
class ScriptPreviewHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $field = $this->getEntityManager()->getRepository('EntityFieldTypeScript')->get((string)$request->getAttribute('id'));
        if (empty($field)) {
            throw new NotFound();
        }

        $data = $this->getRequestBody($request);

        $result = $this->getRecordService($field->get('entityId'))->previewScript(
            (string)($data->script ?? $field->get('script')),
            (string)($data->outputType ?? $field->get('outputType') ?? 'text')
        );

        return new JsonResponse($result);
    }
}
