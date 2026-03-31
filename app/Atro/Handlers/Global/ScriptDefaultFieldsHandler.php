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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityScriptDefaultFields',
    methods: [
        'POST',
    ],
    summary: 'Get script default fields',
    description: 'Returns computed Twig default values for all fields of the specified entity that have a script-type default.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['entityName'],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. "Product").',
                            'example'     => 'Product',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Map of field names to their computed default values',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'                 => 'object',
                        'additionalProperties' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName is required',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class ScriptDefaultFieldsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);
        $entityName = (string)($data->entityName ?? '');
        if (empty($entityName)) {
            throw new BadRequest('entityName is required');
        }

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        $seed = $this->getEntityManager()->getRepository($entityName)->get();
        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $seed->getEntityType(), 'fields'], []) as $name => $defs) {
            if (
                !empty($defs['type'])
                && $defs['type'] === 'varchar'
                && !empty($defs['default'])
                && $seed->has($name)
            ) {
                $default = $defs['default'];
                if (strpos($default, '{{') !== false && strpos($default, '}}') !== false) {
                    $result[$name] = $seed->get($name);
                }
            }
        }

        return new JsonResponse($result);
    }
}
