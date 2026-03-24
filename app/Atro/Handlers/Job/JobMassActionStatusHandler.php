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

namespace Atro\Handlers\Job;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Job/action/massActionStatus',
    methods: ['GET'],
    summary: 'Get status of mass action job',
    description: 'Returns the current status of a mass action job.',
    tag: 'Job',
    parameters: [
        ['name' => 'id', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'example' => '613219736ca7a1c68']],
    ],
    responses: [
        200 => ['description' => 'Job status', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['done' => ['type' => 'boolean'], 'errors' => ['type' => 'string', 'nullable' => true], 'message' => ['type' => 'string', 'nullable' => true]]]]]],
    ],
)]
class JobMassActionStatusHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp    = $request->getQueryParams();
        $jobId = $qp['id'] ?? null;

        if (empty($jobId)) {
            throw new BadRequest();
        }

        return new JsonResponse($this->getRecordService('Job')->getMassActionJobStatus((string) $jobId));
    }
}
