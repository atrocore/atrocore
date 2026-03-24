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
    path: '/User/action/resetPassword',
    methods: ['POST'],
    summary: 'Resets a user password',
    description: 'Admin action: generates a new random password and sends it to the user.',
    tag: 'User',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['userId'], 'properties' => ['userId' => ['type' => 'string']]]]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class UserResetPasswordHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'userId')) {
            throw new BadRequest();
        }

        $result = $this->getRecordService('User')->resetPassword($data->userId);

        return new JsonResponse(['true' => $result]);
    }
}
