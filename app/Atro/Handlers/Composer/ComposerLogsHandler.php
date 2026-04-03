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

namespace Atro\Handlers\Composer;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Composer/logs',
    methods: [
        'GET',
    ],
    summary: 'Returns composer logs',
    description: 'Returns a paginated list of composer operation logs. Accessible by administrators only.',
    tag: 'Composer',
    parameters: [
        [
            'name'     => 'offset',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'integer',
                'example' => 0,
            ],
        ],
        [
            'name'     => 'maxSize',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'integer',
                'example' => 20,
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'List of log entries',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total' => [
                                'type' => 'integer',
                            ],
                            'list'  => [
                                'type'  => 'array',
                                'items' => [
                                    'type' => 'object',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class ComposerLogsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $qp      = $request->getQueryParams();
        $offset  = (int) ($qp['offset'] ?? 0);
        $maxSize = (int) ($qp['maxSize'] ?? 20);

        $repo = $this->getEntityManager()->getRepository('Note');

        $result = [
            'list'  => [],
            'total' => $repo->where(['parentType' => 'ModuleManager'])->count(),
        ];

        if ($result['total'] > 0) {
            $result['list'] = $repo
                ->where(['parentType' => 'ModuleManager'])
                ->order('createdAt', 'DESC')
                ->limit($offset, $maxSize)
                ->find()
                ->toArray();
        }

        return new JsonResponse($result);
    }
}
