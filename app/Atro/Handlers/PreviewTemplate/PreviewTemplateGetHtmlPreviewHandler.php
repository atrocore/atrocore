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

namespace Atro\Handlers\PreviewTemplate;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/PreviewTemplate/{id}/htmlPreview',
    methods: [
        'GET',
    ],
    summary: 'Get HTML preview of a template',
    description: 'Renders and returns the HTML preview of the specified preview template applied to the given entity record.',
    tag: 'PreviewTemplate',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the preview template to render',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'entityId',
            'in'          => 'query',
            'required'    => true,
            'description' => 'ID of the entity record the template will be rendered for',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Rendered HTML preview and language metadata',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'htmlPreview'          => [
                                'type'        => 'string',
                                'description' => 'Rendered HTML content of the template',
                            ],
                            'hasMultipleLanguages' => [
                                'type'        => 'boolean',
                                'description' => 'Whether the template output varies by language',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Bad request — entityId query parameter is missing',
        ],
        404 => [
            'description' => 'Not found — no preview template with the given ID exists',
        ],
    ],
)]
class PreviewTemplateGetHtmlPreviewHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id       = (string) $request->getAttribute('id');
        $entityId = (string) ($request->getQueryParams()['entityId'] ?? '');

        return new JsonResponse([
            'htmlPreview'          => $this->getRecordService('PreviewTemplate')->getHtmlPreview($id, $entityId),
            'hasMultipleLanguages' => true,
        ]);
    }
}
