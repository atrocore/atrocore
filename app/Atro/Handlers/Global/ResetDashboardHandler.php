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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/resetDashboard',
    methods: [
        'POST',
    ],
    summary: 'Reset dashboard layout',
    description: 'Resets the dashboard layout and dashlet options for the specified user to the system default. '
        . 'Admins can reset any user; regular users can only reset themselves.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['id'],
                    'properties' => [
                        'id' => [
                            'type'        => 'string',
                            'description' => 'ID of the user whose dashboard should be reset.',
                            'example'     => 'a01k1g09hhce8m8pkmzt3zzyq5v',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Default dashboard layout applied to the user.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'dashboardLayout' => [
                                'type'        => 'array',
                                'nullable'    => true,
                                'description' => 'Default dashboard tab layout, or null if no default is configured.',
                                'items'       => ['type' => 'object'],
                            ],
                            'dashletsOptions' => [
                                'type'        => 'object',
                                'nullable'    => true,
                                'description' => 'Default dashlet options, or null if no default is configured.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'The authenticated user is not allowed to reset another user\'s dashboard.',
        ],
        404 => [
            'description' => 'User with the given id not found.',
        ],
    ],
)]
class ResetDashboardHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!$this->getUser()->isAdmin()) {
            if ($this->getUser()->id != $data->id) {
                throw new Forbidden();
            }
        }

        $user = $this->getEntityManager()->getEntity('User', $data->id);
        if (empty($user)) {
            throw new NotFound();
        }

        $user->set([
            'dashboardLayout' => null,
            'dashletsOptions' => null,
        ]);
        $this->getEntityManager()->saveEntity($user);

        if (empty($defaultLayout = $this->getUser()->get('layoutProfile'))) {
            $defaultLayout = $this->getEntityManager()
                ->getRepository('LayoutProfile')
                ->where(['isDefault' => true])
                ->findOne();
        }

        return new JsonResponse([
            'dashboardLayout' => $defaultLayout?->get('dashboardLayout') ?: null,
            'dashletsOptions' => $defaultLayout?->get('dashletsOptions') ?: null,
        ]);
    }
}
