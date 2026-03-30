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
    path: '/ClusterItem/{id}/confirm',
    methods: [
        'POST',
    ],
    summary: 'Confirm a cluster item',
    description: 'Confirms the specified cluster item. If the item is the master entity type, it is set as the cluster\'s golden record. Otherwise the item\'s staging record is linked to the existing golden record; if no golden record exists yet, one is created automatically (or a new master entity record is created if needed). Returns false if automatic master record creation failed.',
    tag: 'ClusterItem',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the ClusterItem to confirm.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if confirmed successfully, false if automatic master record creation failed.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Current user does not have edit access on ClusterItem.',
        ],
        404 => [
            'description' => 'ClusterItem not found.',
        ],
    ],
)]
class ClusterItemConfirmHandler extends AbstractHandler
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

        return new BoolResponse($recordService->confirm($entity));
    }
}
