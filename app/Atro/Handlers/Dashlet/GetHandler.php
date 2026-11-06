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

namespace Atro\Handlers\Dashlet;

use Atro\Core\Dashlets\DashletInterface;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Dashlet/{dashletName}',
    methods: [
        'GET',
    ],
    summary: 'Get dashlet data',
    description: 'Returns the data of a dashlet. The class providing the data is set as `className` in the metadata of the dashlet (`dashlets.{dashletName}`); the query parameters are passed to it. A dashlet is not bound to an entity: it can combine the data of several entities.',
    tag: 'Dashlet',
    parameters: [
        [
            'name'        => 'dashletName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Dashlet name, e.g. `Channels`, `DataSyncErrorsImport`.',
            'schema'      => [
                'type'    => 'string',
                'example' => 'Channels',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Dashlet data. The structure depends on the dashlet.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'dashletName is missing',
        ],
        403 => [
            'description' => 'No access to the data of the dashlet',
        ],
        404 => [
            'description' => 'No such dashlet found',
        ],
    ],
)]
class GetHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $dashletName = ucfirst((string)$request->getAttribute('dashletName', ''));
        if ($dashletName === '') {
            throw new BadRequest('dashletName is required.');
        }

        $className = $this->getMetadata()->get(['dashlets', $dashletName, 'className']);
        if (empty($className) || !is_a($className, DashletInterface::class, true)) {
            throw new NotFound('No such dashlet found');
        }

        $params = $request->getQueryParams();
        if (array_key_exists('where', $params)) {
            $params['where'] = $this->prepareWhereQuery($params['where']);
        }

        /** @var DashletInterface $dashlet */
        $dashlet = $this->container->get($className);

        return new JsonResponse($dashlet->getData($params));
    }
}
