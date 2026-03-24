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

namespace Atro\Handlers\Composer;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Composer/releaseNotes',
    methods: ['POST'],
    summary: 'Get release notes for a module',
    description: 'Returns release notes HTML for the specified module. Accessible by administrators only.',
    tag: 'Composer',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['id'], 'properties' => ['id' => ['type' => 'string']]]]],
    ],
    responses: [
        200 => ['description' => 'Release notes HTML', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['html' => ['type' => 'string']]]]]],
    ],
)]
class ComposerReleaseNotesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'id') || empty($data->id)) {
            throw new BadRequest();
        }

        return new JsonResponse(['html' => $this->getServiceFactory()->create('Composer')->getReleaseNotes((string) $data->id)]);
    }
}
