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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ClusterItem/{id}/unreject',
    methods: [
        'POST',
    ],
    summary: 'Unreject a cluster item',
    description: 'Moves a previously rejected cluster item back into the active cluster. Requires the ID of the RejectedClusterItem relation record in the request body.',
    tag: 'ClusterItem',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the active ClusterItem that the rejected item will be restored under.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'relationId',
                    ],
                    'properties' => [
                        'relationId' => [
                            'type'        => 'string',
                            'description' => 'ID of the RejectedClusterItem record to unreject.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'ClusterItem successfully unrejected.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'relationId is missing.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on ClusterItem.',
        ],
        404 => [
            'description' => 'ClusterItem, RejectedClusterItem, or associated Cluster not found.',
        ],
    ],
)]
class ClusterItemUnrejectHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $id   = (string)$request->getAttribute('id');
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'relationId')) {
            throw new BadRequest('Rejected cluster item id is required.');
        }

        $this->getRecordService('ClusterItem')->unreject($id, (string) $data->relationId);

        return new BoolResponse(true);
    }
}
