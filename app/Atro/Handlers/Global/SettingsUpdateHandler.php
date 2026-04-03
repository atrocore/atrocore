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
        'PATCH',
    ],
    summary: 'Update system settings',
    description: 'Updates one or more system configuration fields. Requires administrator privileges. '
        . 'Only the fields provided in the request body are updated; omitted fields remain unchanged. '
        . 'Password-type fields are accepted but never returned in the response. '
        . 'Returns the full updated settings object identical to `GET /settings`.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'                 => 'object',
                    'description'          => 'Partial settings object. Only provided fields are updated.',
                    'additionalProperties' => true,
                    'example'             => [
                        'language'   => 'de_DE',
                        'dateFormat' => 'DD.MM.YYYY',
                        'timeZone'   => 'Europe/Berlin',
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Full updated settings object. Same structure as `GET /settings`.',
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
class SettingsUpdateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        /** @var \Atro\Services\Settings $service */
        $service = $this->getServiceFactory()->create('Settings');

        return new JsonResponse($service->update($data));
    }
}
