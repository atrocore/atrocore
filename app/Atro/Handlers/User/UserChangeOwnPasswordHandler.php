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
    path: '/User/action/changeOwnPassword',
    methods: [
        'POST',
    ],
    summary: 'Changes own password',
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
                            'type' => 'string',
                        ],
                        'currentPassword' => [
                            'type' => 'string',
                        ],
                        'userId'          => [
                            'type' => 'string',
                        ],
                        'sendAccessInfo'  => [
                            'type' => 'boolean',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Success',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
    ],
)]
class UserChangeOwnPasswordHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->getConfig()->get('resetPasswordViaEmailOnly', false)) {
            throw new BadRequest($this->getLanguage()->translate('changePasswordOnResetViaEmailOnly', 'messages', 'User'));
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'password') || !property_exists($data, 'currentPassword')) {
            throw new BadRequest();
        }

        $result = $this->getRecordService('User')->changePassword(
            $data->userId ?? $this->getUser()->id,
            $data->password,
            true,
            $data->currentPassword,
            $data->sendAccessInfo ?? false
        );

        return new BoolResponse(true);
    }
}
