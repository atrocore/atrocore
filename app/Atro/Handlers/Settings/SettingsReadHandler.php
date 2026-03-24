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

namespace Atro\Handlers\Settings;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Settings',
    methods: ['GET'],
    auth: false,
    summary: 'Returns system settings',
    description: 'Returns system configuration data available to the current user.',
    tag: 'Settings',
    responses: [
        200 => ['description' => 'Settings data', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
class SettingsReadHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var \Atro\Services\Settings $service */
        $service = $this->getServiceFactory()->create('Settings');

        return new JsonResponse($service->getConfigData());
    }
}
