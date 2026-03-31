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
use Atro\Core\Utils\DataUtil;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/metadata',
    methods: [
        'GET',
    ],
    summary: 'Get application metadata',
    description: 'Returns the full metadata tree: `entityDefs` (field definitions per entity), '
        . '`clientDefs` (UI configuration per entity), `scopes` (entity-level flags and settings), '
        . '`app` (global application config, authentication types, system icons, etc.). '
        . 'Paths listed in `app.frontendHiddenPathList` are stripped before the response is sent. '
        . 'The result is heavily cached by the client — it is only re-fetched when the data timestamp changes.',
    tag: 'Global',
    responses: [
        200 => [
            'description' => 'Full metadata tree. Top-level keys include `entityDefs`, `clientDefs`, `scopes`, and `app`.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'entityDefs' => [
                                'type'        => 'object',
                                'description' => 'Field and relationship definitions keyed by entity name.',
                            ],
                            'clientDefs' => [
                                'type'        => 'object',
                                'description' => 'Frontend UI configuration keyed by entity name (views, panels, actions, icons, etc.).',
                            ],
                            'scopes'     => [
                                'type'        => 'object',
                                'description' => 'Entity-level flags keyed by entity name (type, acl, stream, tab visibility, etc.).',
                            ],
                            'app'        => [
                                'type'        => 'object',
                                'description' => 'Global application metadata (authentication types, system icons, reference data, etc.).',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    hidden: false,
    skipActionHistory: true,
)]
class MetadataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new JsonResponse(DataUtil::toArray($this->getMetadata()->getAllForFrontend()));
    }
}
