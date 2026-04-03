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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/settings',
    methods: [
        'GET',
    ],
    summary: 'Get system settings',
    description: 'Returns the system configuration available to the current user. '
        . 'Does not require authentication — it is loaded before login to render the login page (theme, language, logo, etc.). '
        . 'The set of returned fields depends on the caller\'s role: '
        . 'administrators receive the full configuration, regular users receive a restricted subset. '
        . 'Password-type fields are always stripped from the response regardless of the caller\'s role. '
        . 'In addition to raw config fields the response always includes: '
        . '`jsLibs` (JS library definitions for dynamic script loading), '
        . '`themes` (available UI themes), '
        . '`coreVersion` (installed AtroCore version), '
        . '`matchings` (matching configuration records), '
        . 'and `matchingRules` (matching rule records).',
    tag: 'Global',
    auth: false,
    responses: [
        200 => [
            'description' => 'System settings for the current user.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'    => 'object',
                        'example' => [
                            'language'       => 'en_US',
                            'dateFormat'     => 'MM/DD/YYYY',
                            'timeFormat'     => 'HH:mm',
                            'timeZone'       => 'UTC',
                            'weekStart'      => 0,
                            'defaultCurrency' => 'USD',
                            'coreVersion'    => '1.14.0',
                            'jsLibs'         => ['jsTree' => ['path' => 'client/lib/jstree.min.js', 'exportsTo' => 'jQuery']],
                            'themes'         => ['AtroCore' => ['stylesheet' => 'client/css/atrocore.css']],
                            'matchings'      => [],
                            'matchingRules'  => [],
                        ],
                    ],
                ],
            ],
        ],
    ],
    skipActionHistory: true,
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
