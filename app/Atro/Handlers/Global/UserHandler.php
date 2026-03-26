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
    path: '/App/user',
    methods: ['GET'],
    summary: 'Get authorized user data',
    description: 'Generate authorization token and return authorized user data.',
    tag: 'Global',
    auth: true,
    parameters: [
        ['name' => 'Authorization-Token-Only', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'boolean', 'example' => true]],
        ['name' => 'Authorization-Token-Lifetime', 'in' => 'header', 'required' => false, 'description' => 'Lifetime should be set in hours. 0 means no expiration. If this parameter is not passed, the globally configured parameter is used.', 'schema' => ['type' => 'integer', 'example' => 0]],
        ['name' => 'Authorization-Token-Idletime', 'in' => 'header', 'required' => false, 'description' => 'Idletime should be set in hours. 0 means no expiration. If this parameter is not passed, the globally configured parameter is used.', 'schema' => ['type' => 'integer', 'example' => 0]],
    ],
    responses: [
        200 => ['description' => 'Authorized user data', 'content' => ['application/json' => ['schema' => [
            'type'       => 'object',
            'properties' => [
                'authorizationToken' => ['type' => 'string', 'example' => 'YWRtaW46NGQ1NGU5ZTEzYjc0NGQzOGM5ODM2NzIyNDU2YTZmNjk='],
            ],
        ]]]],
    ],
)]
class UserHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getServiceFactory()->create('App')->getUserData();
        $data['authorizationToken'] = base64_encode("{$data['user']->userName}:{$data['user']->token}");

        $tokenOnly = $request->getHeaderLine('Authorization-Token-Only');
        if ($tokenOnly === 'true' || $tokenOnly === '1') {
            return new JsonResponse(['authorizationToken' => $data['authorizationToken']]);
        }

        return new JsonResponse($data);
    }
}
