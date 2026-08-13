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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/checkBackgroundProcessing',
    methods: [
        'GET',
    ],
    summary: 'Check whether background processing works',
    description: 'Checks whether the daemons which process background tasks are running. '
    . 'The check takes about two seconds, because it waits for a daemon to pick up a check-up file. '
    . 'A negative result usually means that the crontab is not configured correctly '
    . 'or that there is a problem on the server. Accessible by administrators only.',
    tag: 'Global',
    responses: [
        200 => [
            'description' => 'true if background processing works, false if the daemons are not running.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Current user is not an administrator.',
        ],
    ],
    skipActionHistory: true,
)]
class CheckBackgroundProcessingHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return new BoolResponse($this->getRecordService('SoftwarePackage')->isDaemonAlive());
    }
}
