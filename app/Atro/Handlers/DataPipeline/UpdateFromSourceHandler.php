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

namespace Atro\Handlers\DataPipeline;

use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/DataPipeline/updateFromSource',
    methods: [
        'POST',
    ],
    summary: 'Update a target record from its source record',
    description: 'Finds the data pipeline whose source record is linked to the given target record and renders the transformation script of that pipeline to update the target record. A target record can be linked to only one source record.',
    tag: 'DataPipeline',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['entityName', 'entityId'],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Target entity name of the data pipelines (e.g. "Product").',
                        ],
                        'entityId'   => [
                            'type'        => 'string',
                            'description' => 'ID of the target entity record to update.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true when the transformation is executed',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'No source record is linked to the target record, or the transformation script is missing or invalid.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on the target record or read access on the linked source record, or the transformation user is not allowed to edit the target record.',
        ],
        404 => [
            'description' => 'The entity is not a target of any data pipeline, or the target record is not found.',
        ],
    ],
)]
class UpdateFromSourceHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $this->getRecordService('DataPipeline')->updateFromSource((string)$data->entityName, (string)$data->entityId);

        return new BoolResponse(true);
    }
}
