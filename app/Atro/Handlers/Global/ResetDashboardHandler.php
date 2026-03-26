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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/UserProfile/action/resetDashboard',
    methods: ['POST'],
    summary: 'Reset dashboard layout',
    description: 'Resets the dashboard layout and dashlet options for a user to the default. Admins can reset any user; regular users can only reset their own.',
    tag: 'Global',
    responses: [
        200 => ['description' => 'Reset dashboard data', 'content' => ['application/json' => ['schema' => [
            'type'       => 'object',
            'properties' => [
                'dashboardLayout' => ['nullable' => true],
                'dashletsOptions' => ['nullable' => true],
            ],
        ]]]],
        400 => ['description' => 'id is required'],
        403 => ['description' => 'Forbidden'],
        404 => ['description' => 'User not found'],
    ],
)]
class ResetDashboardHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->id)) {
            throw new BadRequest();
        }

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
