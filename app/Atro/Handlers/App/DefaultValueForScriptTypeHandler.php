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

namespace Atro\Handlers\App;

use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Core\Utils\Metadata;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/App/action/defaultValueForScriptType',
    methods: ['GET'],
    summary: 'Get default value for a script-type field',
    description: 'Returns the evaluated default value for a field with defaultValueType=script.',
    tag: 'App',
    parameters: [
        ['name' => 'entityName', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
        ['name' => 'field', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Default value', 'content' => ['application/json' => ['schema' => [
            'type'       => 'object',
            'properties' => [
                'default' => ['nullable' => true],
            ],
        ]]]],
        400 => ['description' => 'entityName and field are required'],
    ],
)]
class DefaultValueForScriptTypeHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly Metadata  $metadata,
        private readonly Container $container
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (empty($query['entityName']) || empty($query['field'])) {
            throw new BadRequest("'entityName' and 'field' params are required.");
        }

        $entityName = $query['entityName'];
        $field      = $query['field'];
        $default    = null;

        $defaultValueType = $this->metadata->get("entityDefs.{$entityName}.fields.{$field}.defaultValueType");

        if ($defaultValueType === 'script') {
            $script = $this->metadata->get("entityDefs.{$entityName}.fields.{$field}.default");
            if (!empty($script)) {
                $default = $this->container->get('twig')->renderTemplate((string)$script, []);
            }
        }

        return new JsonResponse(['default' => $default]);
    }
}
