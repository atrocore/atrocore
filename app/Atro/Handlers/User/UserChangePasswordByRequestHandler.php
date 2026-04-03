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
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
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
    auth: false,
    summary: 'Changes password using a reset token',
    description: 'Sets a new password using a password reset token that was previously sent to the user\'s email.',
    tag: 'User',
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
            'description' => 'Result with redirect URL',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'url' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class UserChangePasswordByRequestHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->requestId) || empty($data->password)) {
            throw new BadRequest();
        }

        $p = $this->getEntityManager()->getRepository('PasswordChangeRequest')
            ->where(['requestId' => $data->requestId])
            ->findOne();

        if (!$p) {
            throw new Forbidden();
        }

        $userId = $p->get('userId');
        if (!$userId) {
            throw new Error();
        }

        try {
            $changed = $this->getRecordService('User')->changePassword($userId, $data->password);
        } catch (BadRequest $e) {
            // do not delete request on password validation error
            throw $e;
        } catch (\Throwable $e) {
            $this->getEntityManager()->removeEntity($p);
            throw $e;
        }

        if (!empty($changed)) {
            $this->getEntityManager()->removeEntity($p);
            return new JsonResponse(['url' => $p->get('url')]);
        }

        return new JsonResponse([]);
    }
}
