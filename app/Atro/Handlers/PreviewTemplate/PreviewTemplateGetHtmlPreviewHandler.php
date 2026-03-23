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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/PreviewTemplate/action/getHtmlPreview',
    methods: ['GET'],
    summary: 'Returns HTML preview of a template',
    description: 'Renders and returns the HTML preview of a preview template applied to the specified entity.',
    tag: 'PreviewTemplate',
    parameters: [
        ['name' => 'previewTemplateId', 'in' => 'query', 'required' => true,  'schema' => ['type' => 'string']],
        ['name' => 'entityId',          'in' => 'query', 'required' => true,  'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'HTML preview', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['htmlPreview' => ['type' => 'string'], 'hasMultipleLanguages' => ['type' => 'boolean']]]]]],
    ],
)]
class PreviewTemplateGetHtmlPreviewHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        if (empty($qp['previewTemplateId']) || empty($qp['entityId'])) {
            throw new BadRequest();
        }

        return new JsonResponse([
            'htmlPreview'          => $this->getRecordService('PreviewTemplate')->getHtmlPreview($qp['previewTemplateId'], $qp['entityId']),
            'hasMultipleLanguages' => true,
        ]);
    }
}
