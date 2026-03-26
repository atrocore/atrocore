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
    path: '/GlobalSearch',
    methods: ['GET'],
    summary: 'Global search',
    description: 'Searches across all enabled entity types and returns matching records.',
    tag: 'Global',
    parameters: [
        ['name' => 'q',       'in' => 'query', 'required' => false, 'schema' => ['type' => 'string',  'example' => 'product name']],
        ['name' => 'offset',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 0]],
        ['name' => 'maxSize', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 10]],
    ],
    responses: [
        200 => ['description' => 'Search results', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
class GlobalSearchHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        $result = $this->getServiceFactory()->create('GlobalSearch')->find(
            (string) ($qp['q'] ?? ''),
            (int)    ($qp['offset'] ?? 0),
            (int)    ($qp['maxSize'] ?? 0),
        );

        return new JsonResponse($result);
    }
}
