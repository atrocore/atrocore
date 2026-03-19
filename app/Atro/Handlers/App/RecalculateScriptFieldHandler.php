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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/App/action/recalculateScriptField',
    methods: ['POST'],
    summary: 'Recalculate the value of the script fields',
    description: 'Recalculate the value of the script fields',
    tag: 'App',
    responses: [
        200 => ['description' => 'Updated entity data', 'content' => ['application/json' => ['schema' => [
            'type'    => 'object',
            'example' => ['id' => 'a01k1g09hhce8m8pkmzt3zzyq5v', 'name' => 'Yellow Bike'],
        ]]]],
    ],
)]
class RecalculateScriptFieldHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        return new JsonResponse(
            (array)$this->getServiceFactory()->create('App')->recalculateScriptField($data)->getValueMap()
        );
    }
}
