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
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Action/action/executeRecordAction',
    methods: ['POST'],
    summary: 'Execute a record-level action',
    description: 'Executes a named action on a specific entity record.',
    tag: 'Action',
    parameters: [],
    responses: [
        200 => ['description' => 'Execution result', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
        400 => ['description' => 'actionId and actionType are required'],
    ],
)]
class ExecuteRecordActionHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = json_decode((string)$request->getBody()) ?? new \stdClass();

        if (!property_exists($data, 'actionId') || !property_exists($data, 'actionType')) {
            throw new BadRequest();
        }

        /** @var \Atro\Services\Action $service */
        $service = $this->getServiceFactory()->create('Action');

        return new JsonResponse(
            $service->executeRecordAction(
                (string)$data->actionId,
                (string)($data->entityId ?? ''),
                (string)$data->actionType,
                $data->payload ?? null
            )
        );
    }
}
