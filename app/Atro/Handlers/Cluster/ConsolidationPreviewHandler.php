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

namespace Atro\Handlers\Cluster;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Cluster/{id}/consolidationPreview',
    methods: [
        'POST',
    ],
    summary: 'Preview the consolidation result for a cluster',
    description: 'Renders the consolidation script for the unconfirmed items of the cluster without storing anything, and returns the golden record as it would look after the consolidation. Responds with an error whenever the consolidation script is missing, invalid or fails to render.',
    tag: 'Cluster',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the Cluster to preview the consolidation for.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => false,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'consolidationScript' => [
                            'type'        => 'string',
                            'description' => 'Script to render instead of the stored one, so an unsaved script can be previewed. When omitted, the stored consolidation script is used.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Field values of the golden record as it would look after the consolidation.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The consolidation script is missing, invalid, or there is nothing to preview.',
        ],
        403 => [
            'description' => 'Current user does not have read access on the cluster\'s masterEntity.',
        ],
        404 => [
            'description' => 'Cluster not found.',
        ],
    ],
)]
class ConsolidationPreviewHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string)$request->getAttribute('id');

        $data = $this->getRequestBody($request);

        // the editor in the sidebar previews what is typed, which is not what is stored yet
        $scriptOverride = (string)$data->consolidationScript;

        $preview = $this->getRecordService('Cluster')->buildMasterRecordPreview($id, $scriptOverride);

        return new JsonResponse($preview->toArray());
    }
}
