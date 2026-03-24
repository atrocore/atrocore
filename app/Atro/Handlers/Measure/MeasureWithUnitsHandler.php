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

namespace Atro\Handlers\Measure;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Measure/action/measureWithUnits',
    methods: ['GET'],
    summary: 'Get measure with its active units',
    description: 'Returns a Measure record together with the list of its active Unit records.',
    tag: 'Measure',
    parameters: [
        ['name' => 'id', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Measure record ID'],
    ],
    responses: [
        200 => ['description' => 'Measure record with active units', 'content' => ['application/json' => ['schema' => [
            'allOf' => [
                ['$ref' => '#/components/schemas/Measure'],
                ['type' => 'object', 'properties' => [
                    'units' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Unit']],
                ]],
            ],
        ]]]],
        400 => ['description' => 'id is required'],
        404 => ['description' => 'Measure record not found'],
    ],
    entities: ['Measure', 'Unit'],
)]
class MeasureWithUnitsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = $request->getQueryParams()['id'] ?? null;

        if (empty($id)) {
            throw new BadRequest('id is required');
        }

        $service = $this->getServiceFactory()->create('Measure');

        $entity = $service->readEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        $result = (array)$entity->getValueMap();
        $result['units'] = [];

        $measureUnits = $service->findLinkedEntities($id, 'units', [
            'where'  => [
                [
                    'type'      => 'isTrue',
                    'attribute' => 'isActive',
                ]
            ],
            'sortBy' => 'createdAt',
            'asc'    => true,
        ]);

        if (!empty($measureUnits['collection'][0])) {
            $result['units'] = $measureUnits['collection']->toArray();
        }

        return new JsonResponse($result);
    }
}
