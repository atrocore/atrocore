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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/DataPipeline/{id}/transformationPreview',
    methods: [
        'POST',
    ],
    summary: 'Preview the target record of a data pipeline',
    description: 'Renders the transformation script of the data pipeline for the given source record without storing anything, and returns the target record as it would look after the transformation. Responds with an error whenever the transformation script is missing, invalid or fails to render.',
    tag: 'DataPipeline',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the DataPipeline to preview the target record for.',
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
                    'required'   => ['sourceRecordId'],
                    'properties' => [
                        'sourceRecordId' => [
                            'type'        => 'string',
                            'description' => 'ID of the source entity record to render the transformation script for.',
                        ],
                        'transformationScript'  => [
                            'type'        => 'string',
                            'description' => 'Script to render instead of the stored one, so an unsaved script can be previewed. When omitted, the stored transformation script is used.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Field values of the target record as it would look after the transformation.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The source record ID is not passed, or the transformation script is missing or invalid.',
        ],
        403 => [
            'description' => 'Current user does not have read access on the data pipeline, the source record or the target entity.',
        ],
        404 => [
            'description' => 'Data pipeline or source record not found.',
        ],
    ],
)]
class TransformationPreviewHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string)$request->getAttribute('id');
        $data = $this->getRequestBody($request);

        if (empty($data->sourceRecordId) || !is_string($data->sourceRecordId)) {
            throw new BadRequest('sourceRecordId is required.');
        }

        $transformationScript = property_exists($data, 'transformationScript') ? (string)$data->transformationScript : null;

        $preview = $this->getRecordService('DataPipeline')->buildTargetRecordPreview($id, $data->sourceRecordId, $transformationScript);

        return new JsonResponse($preview->toArray());
    }
}
