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

namespace Atro\Handlers\ScheduledJob;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ScheduledJob/{id}/executeNow',
    methods: ['POST'],
    summary: 'Executes a scheduled job immediately',
    description: 'Triggers the immediate execution of a scheduled job. Requires read access and admin privileges.',
    tag: 'ScheduledJob',
    parameters: [
       ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class ScheduledJobExecuteNowHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');

        if (!$this->getAcl()->check('ScheduledJob', 'read')) {
            throw new Forbidden();
        }

        $result = $this->getRecordService('ScheduledJob')->executeNow($id);

        return new BoolResponse($result);
    }
}
