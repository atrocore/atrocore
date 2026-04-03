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
    path: '/computeEntityScriptField',
    methods: [
        'POST',
    ],
    summary: 'Compute script field value for a saved entity record',
    description: 'Loads an existing entity record by ID, executes the Twig script defined on the specified field '
        . 'using the full record as context, and returns the updated entity value map. '
        . 'Intended for the "recalculate" button on script-type fields in the detail view. '
        . 'Requires edit permission on the entity record and the field. '
        . 'Only applies to fields of type `script` that have a `script` expression defined.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['entityName', 'id', 'field'],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. "Product").',
                            'example'     => 'Product',
                        ],
                        'id'    => [
                            'type'        => 'string',
                            'description' => 'ID of the existing entity record.',
                            'example'     => 'a01k1g09hhce8m8pkmzt3zzyq5v',
                        ],
                        'field' => [
                            'type'        => 'string',
                            'description' => 'Name of the script-type field to compute.',
                            'example'     => 'labelEn',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if the field was successfully computed.',
        ],
        400 => [
            'description' => 'entityName, id or field is missing, or field does not exist.',
        ],
        403 => [
            'description' => 'Edit access denied for the record or the field.',
        ],
        404 => [
            'description' => 'Entity record not found.',
        ],
    ],
)]
class ComputeEntityScriptFieldHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $this->getServiceFactory()->create('App')->computeEntityScriptField(
            $data->entityName,
            $data->id,
            $data->field
        );

        return new BoolResponse(true);
    }
}
