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

namespace Atro\Handlers\User;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/User/changePasswordByRequest',
    methods: [
        'POST',
    ],
    summary: 'Changes password using a reset token',
    description: 'Sets a new password using a password reset token that was previously sent to the user\'s email.',
    tag: 'User',
    auth: false,
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'requestId',
                        'password',
                    ],
                    'properties' => [
                        'requestId' => [
                            'type'    => 'string',
                            'example' => 'a1b2c3d4e5f6',
                        ],
                        'password'  => [
                            'type'    => 'string',
                            'example' => 'newSecurePassword123',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Password changed successfully.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'url' => [
                                'type'        => 'string',
                                'description' => 'Redirect URL after the password change.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'requestId or password is missing, or the new password is invalid.',
        ],
        404 => [
            'description' => 'Reset token not found.',
        ],
    ],
)]
class UserChangePasswordByRequestHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->requestId)) {
            throw new BadRequest("'requestId' is required.");
        }

        if (empty($data->password)) {
            throw new BadRequest("'password' is required.");
        }

        return new JsonResponse(
            $this->getRecordService('User')->changePasswordByRequest($data->requestId, $data->password)
        );
    }
}
