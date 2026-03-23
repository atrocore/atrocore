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

namespace Atro\Handlers\EntityField;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/EntityField/action/renderScriptPreview',
    methods: ['POST'],
    summary: 'Render script field preview',
    description: 'Renders a preview of a script field value. Accessible by administrators only.',
    tag: 'EntityField',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object']]],
    ],
    responses: [
        200 => ['description' => 'Rendered preview', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
class EntityFieldRenderScriptPreviewHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $data   = $this->getRequestBody($request);
        $result = $this->getRecordService('EntityField')->renderScriptPreview($data);

        return new JsonResponse($result);
    }
}
