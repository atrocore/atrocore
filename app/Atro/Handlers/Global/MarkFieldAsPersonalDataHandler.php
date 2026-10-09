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
    path: '/markFieldAsPersonalData',
    methods: [
        'POST',
    ],
    summary: 'Mark field as personal data',
    description: 'Marks the field of the specified entity record as containing personal data.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'recordId',
                        'field',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Name of the entity. The entity must contain personal data.',
                            'example'     => 'Person',
                        ],
                        'recordId'   => [
                            'type'        => 'string',
                            'description' => 'ID of the entity record.',
                            'example'     => '01a112e3-08e6-7315-a13d-ea9fca7aecba',
                        ],
                        'field'      => [
                            'type'        => 'string',
                            'description' => 'Name of the field to mark as personal data. The field must be marked as personal data and not in every record.',
                            'example'     => 'name',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => '`true` when the field of the record is marked as containing personal data.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The entity does not contain personal data or the field is not marked as "Personal Data" and "Not in every record".',
        ],
        403 => [
            'description' => 'The current user does not have edit access to the record.',
        ],
        404 => [
            'description' => 'The record with the specified `recordId` does not exist in the entity.',
        ],
    ],
)]
class MarkFieldAsPersonalDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $result = $this->getServiceFactory()->create('App')->markFieldAsPersonalData($data->entityName, $data->recordId, $data->field);

        return new BoolResponse($result);
    }
}