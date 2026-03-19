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

namespace Atro\Handlers\App;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/App/logNavigation/{id}',
    methods: ['POST'],
    summary: 'Log navigation event',
    description: 'Records a navigation event for the specified record ID.',
    tag: 'App',
    parameters: [
        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'true if logged', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
        400 => ['description' => 'id is required'],
    ],
)]
class LogNavigationHandler implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = $routeResult ? ($routeResult->getMatchedParams()['id'] ?? null) : null;

        if (empty($id)) {
            throw new BadRequest();
        }

        return new BoolResponse(true);
    }
}
