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
    path: '/User/changeExpiredPassword',
    methods: [
        'POST',
    ],
    summary: 'Change an expired password',
    description: 'Allows the current user to set a new password when their existing password has expired.',
    tag: 'User',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'password',
                    ],
                    'properties' => [
                        'password' => [
                            'type'        => 'string',
                            'description' => 'The new password to set.',
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
            'description' => 'Password is missing.',
        ],
        403 => [
            'description' => 'Forbidden — user is a system user or password has not expired.',
        ],
    ],
)]
class UserChangeExpiredPasswordHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->password)) {
            throw new BadRequest("'password' is required.");
        }

        $this->getRecordService('User')->changeExpiredPassword($data->password);

        return new BoolResponse(true);
    }
}
