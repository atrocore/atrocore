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

namespace Atro\Handlers\ClusterItem;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ClusterItem/{id}/deconsolidate',
    methods: [
        'POST',
    ],
    summary: 'Deconsolidate a single cluster item',
    description: 'Unlinks the record of the specified cluster item from the golden record of its cluster. The item stays in the cluster. The item may not be the master entity item of its cluster.',
    tag: 'ClusterItem',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the ClusterItem to deconsolidate.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if the item was deconsolidated.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The item is the master entity item and cannot be deconsolidated.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on ClusterItem.',
        ],
        404 => [
            'description' => 'ClusterItem not found.',
        ],
    ],
)]
class DeconsolidateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $id            = (string)$request->getAttribute('id');
        $recordService = $this->getRecordService('ClusterItem');

        $entity = $recordService->getEntity($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        $result = $recordService->deconsolidate(['ids' => [$id]]);

        return new BoolResponse($result['count'] > 0);
    }
}
