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

namespace Atro\Handlers\LayoutProfile;

use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\LayoutManager;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/LayoutProfile/{id}/resetAllLayoutToDefault',
    methods: [
        'POST',
    ],
    summary: 'Reset all layouts to default for a layout profile',
    description: 'Removes all custom layout configurations stored under the given layout profile, restoring every entity and view type to its default layout.',
    tag: 'LayoutProfile',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Layout profile record ID',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'All layouts reset to default',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden',
        ],
        404 => [
            'description' => 'Layout profile not found',
        ],
    ],
)]
class LayoutProfileResetAllLayoutToDefaultHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $layoutProfileId = (string)$request->getAttribute('id');

        $layoutManager = $this->getLayoutManager();
        $layoutManager->checkLayoutProfile($layoutProfileId);
        $layoutManager->resetAllToDefault($layoutProfileId);

        return new BoolResponse(true);
    }

    private function getLayoutManager(): LayoutManager
    {
        return $this->container->get('layoutManager');
    }
}
