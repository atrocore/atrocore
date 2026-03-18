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

namespace Atro\Handlers\Action;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Espo\Core\ServiceFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Action/action/executeNow',
    methods: ['POST'],
    summary: 'Execute an action immediately',
    description: 'Executes the specified action record immediately.',
    tag: 'Action',
    parameters: [],
    responses: [
        200 => ['description' => 'Execution result', 'content' => ['application/json' => ['schema' => [
            'type'       => 'object',
            'properties' => [
                'inBackground' => ['type' => 'boolean'],
                'success'      => ['type' => 'boolean'],
                'message'      => ['type' => 'string', 'nullable' => true],
            ],
        ]]]],
        400 => ['description' => 'actionId is required'],
    ],
)]
class ExecuteNowHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly ServiceFactory $serviceFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = json_decode((string)$request->getBody()) ?? new \stdClass();

        if (!property_exists($data, 'actionId')) {
            throw new BadRequest();
        }

        /** @var \Atro\Services\Action $service */
        $service = $this->serviceFactory->create('Action');

        return new JsonResponse(
            $service->executeNow((string)$data->actionId, $data)
        );
    }
}
