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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/evaluateScriptFieldDefault',
    methods: [
        'POST',
    ],
    summary: 'Evaluate script default value for a field',
    description: 'Renders the Twig script defined in `entityDefs.{entityName}.fields.{field}.default` and returns the result as the computed default value. Only applies to fields where `defaultValueType` is `script`.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['entityName', 'field'],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. "Product").',
                            'example'     => 'Product',
                        ],
                        'field'      => [
                            'type'        => 'string',
                            'description' => 'Field name within the entity.',
                            'example'     => 'name',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Default value',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'default' => [
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName and field are required',
        ],
    ],
)]
class DefaultValueForScriptTypeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityName) || empty($data->field)) {
            throw new BadRequest("'entityName' and 'field' params are required.");
        }

        $entityName = $data->entityName;
        $field      = $data->field;
        $default    = null;

        $defaultValueType = $this->getMetadata()->get("entityDefs.{$entityName}.fields.{$field}.defaultValueType");

        if ($defaultValueType === 'script') {
            $script = $this->getMetadata()->get("entityDefs.{$entityName}.fields.{$field}.default");
            if (!empty($script)) {
                $default = $this->container->get('twig')->renderTemplate((string)$script, []);
            }
        }

        return new JsonResponse(['default' => $default]);
    }
}
