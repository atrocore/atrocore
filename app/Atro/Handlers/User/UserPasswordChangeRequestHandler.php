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
    path: '/User/passwordChangeRequest',
    methods: [
        'POST',
    ],
    summary: 'Requests a password reset link',
    description: 'Sends a password reset link to the user\'s email address.',
    tag: 'User',
    auth: false,
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'userName',
                        'emailAddress',
                    ],
                    'properties' => [
                        'userName'     => [
                            'type'    => 'string',
                            'example' => 'admin',
                        ],
                        'emailAddress' => [
                            'type'    => 'string',
                            'example' => 'admin@example.com',
                        ],
                        'url'          => [
                            'type'    => 'string',
                            'example' => 'https://your-instance.com/reset-password',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Reset link sent successfully.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'userName or emailAddress is missing.',
        ],
    ],
)]
class UserPasswordChangeRequestHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->userName)) {
            throw new BadRequest("'userName' is required.");
        }

        if (empty($data->emailAddress)) {
            throw new BadRequest("'emailAddress' is required.");
        }

        $url = property_exists($data, 'url') ? $data->url : null;

        $this->getRecordService('User')->passwordChangeRequest($data->userName, $data->emailAddress, $url);

        return new BoolResponse(true);
    }
}
