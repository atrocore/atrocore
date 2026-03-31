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

use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/logNavigation',
    methods: [
        'POST',
    ],
    summary: 'Log navigation to a non-entity page',
    description: 'Records a navigation event for pages that have no corresponding entity record '
    . '(e.g. Dashboard, Administration, Composer). '
    . 'Writes an ActionHistoryRecord with controllerName="App" so that the LastViewed service '
    . 'can include these pages in the navigation history alongside entity records. '
    . 'The Entity-History request header (tab ID) is stored in the record data and used '
    . 'to isolate history per browser tab. ',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['name', 'url'],
                    'properties' => [
                        'name' => [
                            'type'        => 'string',
                            'description' => 'Page identifier used as targetId in the history record (e.g. "Dashboard", "Administration").',
                            'example'     => 'Dashboard',
                        ],
                        'url'  => [
                            'type'        => 'string',
                            'description' => 'Full browser URL at the time of navigation (pathname + hash).',
                            'example'     => '/#dashboard',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if the navigation event was logged successfully.',
        ],
    ],
    hidden: true,
    skipActionHistory: true,
)]
class LogNavigationHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $record = $this->getEntityManager()->getEntity('ActionHistoryRecord');
        $record->set('controllerName', 'App');
        $record->set('action', 'GET');
        $record->set('targetId', $data->name);
        $record->set('userId', $this->getUser()->id);
        $record->set('authTokenId', $this->getUser()->get('authTokenId'));
        $record->set('ipAddress', $this->getUser()->get('ipAddress'));
        $record->set('authLogRecordId', $this->getUser()->get('authLogRecordId'));
        $record->set('data', [
            'request' => [
                'headers' => ['Entity-History' => [$request->getHeaderLine('Entity-History')]],
                'params'  => [],
                'body'    => ['url' => $data->url],
            ],
        ]);
        $this->getEntityManager()->saveEntity($record);

        return new BoolResponse(true);
    }
}
