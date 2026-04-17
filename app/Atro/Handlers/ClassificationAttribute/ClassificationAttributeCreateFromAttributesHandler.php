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

namespace Atro\Handlers\ClassificationAttribute;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ClassificationAttribute/createFromAttributes',
    methods: [
        'POST',
    ],
    summary: 'Create classification attributes from a list of attributes',
    description: 'Creates one ClassificationAttribute record per entry in attributesIds, all linked to the given classification.',
    tag: 'ClassificationAttribute',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'classificationId',
                        'attributesIds',
                    ],
                    'properties' => [
                        'classificationId' => [
                            'type' => 'string',
                        ],
                        'attributesIds'    => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Records created successfully',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'classificationId is required / attributesIds is required',
        ],
        403 => [
            'description' => 'Forbidden',
        ],
    ],
)]
class ClassificationAttributeCreateFromAttributesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->classificationId)) {
            throw new BadRequest('classificationId is required');
        }

        if (empty($data->attributesIds)) {
            throw new BadRequest('attributesIds is required');
        }

        $this->getRecordService('ClassificationAttribute')->createFromAttributes($data->classificationId, $data->attributesIds);

        return new BoolResponse(true);
    }
}
