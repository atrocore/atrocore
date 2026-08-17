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

namespace Atro\Handlers\Team;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Team/userIds',
    methods: [
        'GET',
    ],
    summary: 'Get user ids of teams',
    description: 'Returns the ids of users belonging to any of the given teams. Used to resolve team membership for condition checks without requiring read access to the TeamUser relation entity.',
    tag: 'Team',
    parameters: [
        [
            'name'        => 'teamIds',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Team record ids to look up members for',
            'schema'      => [
                'type'  => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'User ids belonging to any of the given teams',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'userIds' => [
                                'type'        => 'array',
                                'description' => 'Ids of users belonging to any of the given teams',
                                'items'       => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class UserIdsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $teamIds = (array)($request->getQueryParams()['teamIds'] ?? []);

        return new JsonResponse(['userIds' => $this->getRecordService('Team')->getUserIds($teamIds)]);
    }
}
