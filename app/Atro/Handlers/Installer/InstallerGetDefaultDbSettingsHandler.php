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

namespace Atro\Handlers\Installer;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Installer/action/getDefaultDbSettings',
    methods: [
        'GET',
    ],
    summary: 'Get default database settings',
    description: 'Returns the default database connection settings. Only accessible before installation.',
    tag: 'Installer',
    installerOnly: true,
    auth: false,
    responses: [
        200 => [
            'description' => 'Default DB settings',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
    ],
)]
class InstallerGetDefaultDbSettingsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var \Atro\Services\Installer $installer */
        $installer = $this->getServiceFactory()->create('Installer');

        if ($installer->isInstalled()) {
            throw new Forbidden();
        }

        return new JsonResponse($installer->getDefaultDbSettings());
    }
}
