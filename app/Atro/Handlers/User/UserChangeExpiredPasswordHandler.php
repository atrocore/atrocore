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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/User/action/changeExpiredPassword',
    methods: ['POST'],
    summary: 'Changes an expired password',
    description: 'Allows the current user to set a new password when their existing password has expired.',
    tag: 'User',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['password'], 'properties' => ['password' => ['type' => 'string']]]]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class UserChangeExpiredPasswordHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'password')) {
            throw new BadRequest();
        }

        $user       = $this->getUser();
        $expireDays = $this->getConfig()->get('passwordExpireDays', 0);

        if ($user->isSystemUser() || !$user->needToUpdatePassword($expireDays)) {
            throw new Forbidden();
        }

        $result = $this->getRecordService('User')->changePassword($user->id, $data->password);

        return new JsonResponse(['true' => $result]);
    }
}
