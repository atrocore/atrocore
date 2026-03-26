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

namespace Atro\Core;

use Atro\Core\Routing\Route as RouteAttribute;
use Atro\Core\Routing\RouteCompiler;
use Atro\Core\Utils\Metadata;
use Atro\Services\Composer;
use Atro\Core\Utils\Util;

class OpenApiGenerator
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getSchemaForRoute(array $route): array
    {
        $result = $this->getBase();
        $this->pushRoute($result, $route);

        return $result;
    }

    public function getSchemaForHandler(RouteAttribute $routeAttr, string $entityName = ''): array
    {
        $result = $this->getBase();

        foreach ($routeAttr->entities as $ent) {
            $this->buildEntitySchema($result, $ent);
        }

        if ($entityName !== '') {
            $this->buildEntitySchema($result, $entityName);
        }

        $this->pushHandlerRoute($result, $routeAttr, $entityName);

        return $result;
    }

    public function getFullSchema(): array
    {
        $result = $this->getBase();

        $this->pushCompiledRoutes($result);

        foreach ($this->getMetadata()->get(['entityDefs'], []) as $entityName => $data) {
            $scopeData = $this->getMetadata()->get(['scopes', $entityName]);

            if (empty($data['fields']) || empty($scopeData['entity']) || !empty($scopeData['openApiHidden'])) {
                continue;
            }

            $this->buildEntitySchema($result, $entityName);
        }

        return $result;
    }

    public static function prepareResponses(array $success): array
    {
        return [
            "200" => [
                "description" => "OK",
                "content"     => [
                    "application/json" => [
                        "schema" => $success
                    ]
                ]
            ],
            "304" => [
                "description" => "Not Modified"
            ],
            "400" => [
                "description" => "Bad Request"
            ],
            "401" => [
                "description" => "Unauthorized"
            ],
            "403" => [
                "description" => "Forbidden"
            ],
            "404" => [
                "description" => "Not Found"
            ],
            "500" => [
                "description" => "Internal Server Error"
            ],
        ];
    }

    public static function prepareRouteResponses(array $responseSchema): array
    {
        $responses = self::prepareResponses($responseSchema);
        // Plain-text responses are sent with Content-Type: application/json by the framework,
        // but the body is not JSON-encoded. Remove the content schema so the validator
        // does not attempt JSON parsing on the body.
        if (($responseSchema['type'] ?? '') === 'string') {
            unset($responses['200']['content']);
        }
        return $responses;
    }

    protected function buildEntitySchema(array &$result, string $entityName): void
    {
        if (isset($result['components']['schemas'][$entityName])) {
            return;
        }

        $data = $this->getMetadata()->get(['entityDefs', $entityName]);

        if (empty($data['fields'])) {
            return;
        }

        $result['components']['schemas'][$entityName] = [
            'type'       => 'object',
            'properties' => [
                'id'    => ['type' => 'string', 'readOnly' => true],
                '_meta' => ['type' => 'object', 'readOnly' => true],
            ],
        ];

        foreach ($data['fields'] as $fieldName => $fieldData) {
            if ($fieldName === '_meta') {
                continue;
            }

            $this->getFieldSchema($result, $entityName, $fieldName, $fieldData);
        }

        // Mark all non-required typed properties as nullable so the response validator
        // accepts null values for optional fields (which the API commonly returns).
        // Also mark protected and forRead fields as readOnly, then remove the internal
        // forRead marker so it doesn't leak into the OpenAPI output.
        $required = $result['components']['schemas'][$entityName]['required'] ?? [];
        foreach ($result['components']['schemas'][$entityName]['properties'] as $prop => &$propSchema) {
            if (!in_array($prop, $required, true) && isset($propSchema['type'])) {
                $propSchema['nullable'] = true;
            }

            // forRead can be set either on the metadata field itself or on the property
            // by getFieldSchema (e.g. {field}Name, {field}Names derived properties).
            $fieldData = $data['fields'][$prop] ?? [];
            if (!empty($fieldData['protected']) || !empty($fieldData['forRead']) || !empty($propSchema['forRead'])) {
                $propSchema['readOnly'] = true;
            }

            unset($propSchema['forRead']);
        }
        unset($propSchema);

        $this->buildEntityPostSchema($result, $entityName);
    }

    private function buildEntityPostSchema(array &$result, string $entityName): void
    {
        $readProps    = $result['components']['schemas'][$entityName]['properties'];
        $readRequired = $result['components']['schemas'][$entityName]['required'] ?? [];

        $excluded = ['_meta', 'deleted', 'createdAt', 'modifiedAt', 'createdById'];

        $writeProps = [
            'id' => ['type' => 'string', 'nullable' => true],
        ];
        foreach ($readProps as $prop => $propSchema) {
            if (in_array($prop, $excluded, true) || str_starts_with($prop, '_') || $prop === 'id') {
                continue;
            }
            if (!empty($propSchema['readOnly'])) {
                continue;
            }
            unset($propSchema['readOnly']);
            $propSchema['nullable'] = true;
            $writeProps[$prop] = $propSchema;
        }

        $writeRequired = array_values(array_filter($readRequired, fn($k) => isset($writeProps[$k])));

        $schema = ['type' => 'object', 'properties' => $writeProps];
        if (!empty($writeRequired)) {
            $schema['required'] = $writeRequired;
        }

        $result['components']['schemas']["{$entityName}Post"] = $schema;

        $patchSchema = $schema;
        unset($patchSchema['required']);
        unset($patchSchema['properties']['id']);
        $result['components']['schemas']["{$entityName}Patch"] = $patchSchema;
    }

    protected function getFieldSchema(array &$result, string $entityName, string $fieldName, array $fieldData)
    {
        if (!empty($fieldData['openApiDisabled'])) {
            return;
        }

        if (empty($fieldData['openApiEnabled']) && !empty($fieldData['noLoad'])) {
            return;
        }

        if ($fieldData['type'] === 'script') {
            $fieldData['type'] = $fieldData['outputType'] ?? 'text';
        }

        if (!empty($fieldData['required'])) {
            if (empty($result['components']['schemas'][$entityName]['required'])) {
                $result['components']['schemas'][$entityName]['required'] = [];
            }

            switch ($fieldData['type']) {
                case 'link':
                case 'file':
                case 'linkParent':
                    $result['components']['schemas'][$entityName]['required'][] = "{$fieldName}Id";
                    break;
                case 'linkMultiple':
                    $result['components']['schemas'][$entityName]['required'][] = "{$fieldName}Ids";
                    break;
                default:
                    $result['components']['schemas'][$entityName]['required'][] = $fieldName;
                    break;
            }
        }

        switch ($fieldData['type']) {
            case "autoincrement":
            case "int":
                $result['components']['schemas'][$entityName]['properties'][$fieldName] = [
                    'type'    => 'integer',
                    'minimum' => -2147483648,
                    'maximum' => 2147483647,
                ];
                break;
            case "float":
                $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'number'];
                break;
            case "bool":
                $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'boolean'];
                break;
            case "jsonArray":
            case "jsonObject":
                $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'object'];
                break;
            case "array":
            case "multiEnum":
                $result['components']['schemas'][$entityName]['properties'][$fieldName] = [
                    'type'  => 'array',
                    'items' => ['type' => 'string']
                ];
                break;
            case "file":
            case "link":
            case "linkParent":
                $result['components']['schemas'][$entityName]['properties']["{$fieldName}Id"] = ['type' => 'string'];
                if (!empty($fieldData['protected'])) {
                    $result['components']['schemas'][$entityName]['properties']["{$fieldName}Id"]['forRead'] = true;
                }
                $result['components']['schemas'][$entityName]['properties']["{$fieldName}Name"] = [
                    'type'    => 'string',
                    'forRead' => true
                ];
                break;
            case "extensibleEnum":
                $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'string'];
                $result['components']['schemas'][$entityName]['properties']["{$fieldName}Name"] = [
                    'type'    => 'string',
                    'forRead' => true
                ];
                $result['components']['schemas'][$entityName]['properties']["{$fieldName}OptionData"] = [
                    'type'       => 'object',
                    'properties' => $this->getEnumOptionProperties(),
                    'forRead'    => true
                ];
                break;
            case 'extensibleMultiEnum':
                $result['components']['schemas'][$entityName]['properties'][$fieldName] = [
                    'type'  => 'array',
                    'items' => ['type' => 'string']
                ];
                $result['components']['schemas'][$entityName]['properties']["{$fieldName}Names"] = [
                    'type'    => 'object',
                    'forRead' => true
                ];
                $result['components']['schemas'][$entityName]['properties']["{$fieldName}OptionsData"] = [
                    'type'    => 'array',
                    'forRead' => true,
                    'items'   => [
                        'type'       => 'object',
                        'properties' => $this->getEnumOptionProperties()
                    ]
                ];
                break;
            case "linkMultiple":
                $result['components']['schemas'][$entityName]['properties']["{$fieldName}Ids"] = [
                    'type'  => 'array',
                    'items' => ['type' => 'string']
                ];

                if (!empty($fieldData['protected'])) {
                    $result['components']['schemas'][$entityName]['properties']["{$fieldName}Ids"]['forRead'] = true;
                }

                $result['components']['schemas'][$entityName]['properties']["{$fieldName}Names"] = [
                    'type'    => 'object',
                    'forRead' => true
                ];
                break;
            default:
                $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'string'];
        }

        if (!empty($fieldData['protected']) && !empty($result['components']['schemas'][$entityName]['properties'][$fieldName])) {
            $result['components']['schemas'][$entityName]['properties'][$fieldName]['forRead'] = true;
        }
    }

    protected function getEnumOptionProperties()
    {
        $config = $this->container->get('config');

        $fields = [
            'id'    => [
                'type' => 'string'
            ],
            'code'  => [
                'type' => 'string'
            ],
            'color' => [
                'type' => 'string'
            ],
            'name'  => [
                'type' => 'string'
            ]
        ];

        if (!empty($config->get('isMultilangActive'))) {
            foreach ($config->get('inputLanguageList') ?? [] as $language) {
                $fields["name" . ucfirst(Util::toCamelCase(strtolower($language)))] = [
                    'type' => 'string'
                ];
            }
        }
        return array_merge($fields, [
            'sortOrder'    => [
                'type' => 'integer'
            ],
            'sorting'      => [
                'type' => 'integer'
            ],
            'description'  => [
                'type' => 'string'
            ],
            'preparedName' => [
                'type' => 'string'
            ]
        ]);
    }

    protected function getBase(): array
    {
        return [
            'openapi'    => '3.0.0',
            'info'       => [
                'version'     => Composer::getCoreVersion(),
                'title'       => 'AtroCore REST API documentation',
                'description' => "This is a REST API documentation for AtroCore data platform and its modules (AtroPIM, AtroDAM and others), which is based on [OpenAPI (Swagger) Specification](https://swagger.io/specification/). You can generate your client [here](https://openapi-generator.tech/docs/generators).<br><br><h3>Video tutorials:</h3><ul><li>[How to authorize?](https://youtu.be/GWfNRvCswXg)</li><li>[How to select specific fields?](https://youtu.be/i7o0aENuyuY)</li><li>[How to filter data records?](https://youtu.be/irgWkN4wlkM)</li></ul>",
                'license'     => [
                    'name' => 'GPLv3',
                    'url'  => 'https://www.gnu.org/licenses/gpl-3.0.html',
                ],
            ],
            'servers'    => [
                [
                    'url' => '/api'
                ]
            ],
            'tags'       => [
                ['name' => 'Global', 'description' => 'System-wide actions and universal operations not bound to any specific entity.']
            ],
            'paths'      => [],
            'components' => [
                'securitySchemes' => [
                    'basicAuth'           => [
                        'type'   => 'http',
                        'scheme' => 'basic',
                    ],
                    'Authorization-Token' => [
                        'type' => 'apiKey',
                        'name' => 'Authorization-Token',
                        'in'   => 'header'
                    ],
                    'cookieAuth'          => [
                        'type' => 'apiKey',
                        'name' => 'auth-token',
                        'in'   => 'cookie'
                    ]
                ]
            ]
        ];
    }

    protected function pushRoute(array &$result, array $route): void
    {
        if (!empty($route['description'])) {
            $routePath = preg_replace('/:(\w+)/', '{$1}', $route['route']);
            $row = [
                'tags'        => [$route['params']['controller']],
                'summary'     => $route['summary'] ?? $route['description'],
                'description' => $route['description'],
                'operationId' => md5("{$routePath}_{$route['method']}"),
                "responses"   => self::prepareRouteResponses($route['response'])
            ];

            if (!isset($route['conditions']['auth']) || $route['conditions']['auth'] !== false) {
                $row['security'] = [['Authorization-Token' => []], ['basicAuth' => []], ['cookieAuth' => []]];
            } else {
                $row['security'] = [];
            }
            if (!empty($route['security'])) {
                $row['security'] = $route['security'];
            }
            if (!empty($route['requestParameters'])) {
                $row['parameters'] = $route['requestParameters'];
            }
            if (!empty($route['requestBody'])) {
                $row['requestBody'] = $route['requestBody'];
            }

            $result['paths'][$routePath][$route['method']] = $row;
        }
    }

    private function pushHandlerRoute(array &$result, RouteAttribute $routeAttr, string $entityName = ''): void
    {
        if (empty($routeAttr->responses)) {
            return;
        }

        foreach ($routeAttr->entities as $ent) {
            $this->buildEntitySchema($result, $ent);
        }

        $tag = $routeAttr->tag;

        if (!in_array($tag, array_column($result['tags'], 'name'), true)) {
            $result['tags'][] = ['name' => $tag, 'description' => "$tag endpoints."];
        }

        $responses = [];
        foreach ($routeAttr->responses as $code => $response) {
            $responses[(string) $code] = $response;
        }

        foreach (array_map('strtolower', (array) $routeAttr->methods) as $method) {
            $row = [
                'tags'        => [$tag],
                'summary'     => $routeAttr->summary,
                'description' => $routeAttr->description,
                'operationId' => md5($routeAttr->path . '_' . $method),
                'responses'   => $responses,
                'security'    => $routeAttr->auth ? [['Authorization-Token' => []], ['basicAuth' => []], ['cookieAuth' => []]] : [],
            ];

            if (!empty($routeAttr->parameters)) {
                $row['parameters'] = $routeAttr->parameters;
            }

            if (!empty($routeAttr->requestBody)) {
                $requestBody = $routeAttr->requestBody;
                if ($entityName !== '') {
                    $requestBody = $this->substituteWriteSchemaRef($requestBody, $entityName);
                }
                $row['requestBody'] = $requestBody;
            }

            $result['paths'][$routeAttr->path][$method] = $row;
        }
    }

    private function substituteWriteSchemaRef(array $data, string $entityName): array
    {
        foreach ($data as $key => $value) {
            if ($key === 'schema' && $value === ['x-entity-post' => true]) {
                $data[$key] = ['$ref' => "#/components/schemas/{$entityName}Post"];
            } elseif ($key === 'schema' && $value === ['x-entity-patch' => true]) {
                $data[$key] = ['$ref' => "#/components/schemas/{$entityName}Patch"];
            } elseif (is_array($value)) {
                $data[$key] = $this->substituteWriteSchemaRef($value, $entityName);
            }
        }
        return $data;
    }

    private function pushCompiledRoutes(array &$result): void
    {
        foreach ($this->container->get(RouteCompiler::class)->getCompiledRoutes() as $entry) {
            if (empty($entry['openapi'])) {
                continue;
            }

            foreach ($entry['schemaEntities'] as $entityName) {
                $this->buildEntitySchema($result, $entityName);
            }

            $path = substr($entry['path'], strlen('/api'));
            $tag  = $entry['openapi']['tags'][0] ?? null;

            if ($tag && !in_array($tag, array_column($result['tags'], 'name'), true)) {
                $result['tags'][] = ['name' => $tag, 'description' => "$tag endpoints."];
            }

            foreach ($entry['methods'] as $method) {
                $result['paths'][$path][strtolower($method)] = $entry['openapi'];
            }
        }
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }
}
