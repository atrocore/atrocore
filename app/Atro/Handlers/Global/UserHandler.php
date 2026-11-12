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
    path: '/userSession',
    methods: [
        'GET',
    ],
    summary: 'Get authorized user data',
    description: 'Returns the data of the authenticated user: the user record, the access control, the preferences, the settings and the application parameters. The user interface loads it after signing in.',
    tag: 'Global',
    auth: true,
    responses: [
        200 => [
            'description' => 'Authorized user data.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'user'        => [
                                'type'        => 'object',
                                'description' => 'Authenticated user record.'
                            ],
                            'acl'         => [
                                'type'        => 'object',
                                'description' => 'ACL scope-permission map for the authenticated user.'
                            ],
                            'preferences' => [
                                'type'        => 'object',
                                'description' => 'User UI preferences.'
                            ],
                            'settings'    => [
                                'type'        => 'object',
                                'description' => 'Application settings visible to the authenticated user.'
                            ],
                            'appParams'   => [
                                'type'        => 'object',
                                'description' => 'Runtime application parameters.'
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    skipActionHistory: true,
)]
class UserHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getServiceFactory()->create('Global')->getUserData();
        unset($data['user']->token);

        return new JsonResponse($data);
    }
}
