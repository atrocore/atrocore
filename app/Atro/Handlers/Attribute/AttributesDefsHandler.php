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

namespace Atro\Handlers\Attribute;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Attribute/attributesDefs',
    methods: [
        'GET',
    ],
    summary: 'Get attributes definitions for a set of attributes',
    description: 'Returns field definitions for the specified attributes.',
    tag: 'Attribute',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'query',
            'required'    => true,
            'schema'      => [
                'type' => 'string',
            ],
            'description' => 'Entity name',
        ],
        [
            'name'        => 'attributesIds',
            'in'          => 'query',
            'required'    => true,
            'schema'      => [
                'type'  => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ],
            'description' => 'List of attribute IDs',
        ],
    ],
    responses: [
        200 => [
            'description' => 'Attributes definitions',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName and attributesIds are required',
        ],
    ],
)]
class AttributesDefsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $query = $request->getQueryParams();

        $result = $this->getServiceFactory()->create('Attribute')->getAttributesDefs(
            $query['entityName'],
            $query['attributesIds']
        );

        return new JsonResponse($result);
    }
}
