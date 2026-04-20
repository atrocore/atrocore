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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/User/changeOwnPassword',
    methods: [
        'POST',
    ],
    summary: 'Change own password',
    description: 'Allows the current user to change their own password by providing the current password.',
    tag: 'User',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'password',
                        'currentPassword',
                    ],
                    'properties' => [
                        'password'        => [
                            'type'        => 'string',
                            'description' => 'The new password to set.',
                        ],
                        'currentPassword' => [
                            'type'        => 'string',
                            'description' => 'The current password for verification.',
                        ],
                        'userId'          => [
                            'type'        => 'string',
                            'description' => 'Target user ID. Defaults to the current user.',
                        ],
                        'sendAccessInfo'  => [
                            'type'        => 'boolean',
                            'description' => 'Whether to send access info to the user after the password change.',
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
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Password or current password is missing, or password change via email only is enforced.',
        ],
        403 => [
            'description' => 'Current password is incorrect.',
        ],
    ],
)]
class UserChangeOwnPasswordHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->password)) {
            throw new BadRequest("'password' is required.");
        }

        if (empty($data->currentPassword)) {
            throw new BadRequest("'currentPassword' is required.");
        }

        $this->getRecordService('User')->changeOwnPassword(
            $data->userId ?? $this->getUser()->id,
            $data->password,
            $data->currentPassword,
            $data->sendAccessInfo ?? false
        );

        return new BoolResponse(true);
    }
}
