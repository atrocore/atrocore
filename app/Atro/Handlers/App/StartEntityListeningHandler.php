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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/App/action/startEntityListening',
    methods: ['POST'],
    summary: 'Start listening to entity changes',
    description: 'Subscribes the current user to real-time updates for the specified entity record.',
    tag: 'App',
    responses: [
        200 => ['description' => 'Listening token data', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
        400 => ['description' => 'entityName and entityId are required'],
    ],
)]
class StartEntityListeningHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = json_decode((string)$request->getBody()) ?? new \stdClass();

        if (empty($data->entityName) || empty($data->entityId)) {
            throw new BadRequest();
        }

        return new JsonResponse(
            $this->container->get('realtimeManager')->startEntityListening($data->entityName, $data->entityId)
        );
    }
}
