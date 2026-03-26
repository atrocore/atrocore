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

use Atro\Core\DataManager;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\JobManager;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/App/action/jobManagerUpdate',
    methods: ['POST'],
    summary: 'Pause or resume the job manager',
    description: 'Pauses or resumes background job processing. Admin only.',
    tag: 'Global',
    responses: [
        200 => ['description' => 'true if updated', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
        403 => ['description' => 'Forbidden'],
    ],
)]
class JobManagerUpdateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'pause')) {
            return new BoolResponse(false);
        }

        if (!empty($data->pause)) {
            file_put_contents(JobManager::PAUSE_FILE, '1');
        } else {
            if (file_exists(JobManager::PAUSE_FILE)) {
                unlink(JobManager::PAUSE_FILE);
            }
        }

        DataManager::pushPublicData('jmPaused', file_exists(JobManager::PAUSE_FILE));

        return new BoolResponse(true);
    }
}
