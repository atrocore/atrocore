<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Espo\Core;

use Espo\Core\Utils\Metadata;

class OpenApiGenerator
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getData(): array
    {
        $result = [
            'openapi'      => '3.0.0',
            'info'         => [
                'version'     => '1.0.0',
                'title'       => 'AtroCore REST API documentation',
                'description' => "This is REST API documentation for AtroCore, AtroPim, AtroDam and other modules, which is based on OpenApi (swagger). You can find out more about Swagger at [http://swagger.io](http://swagger.io). You can generate your client [here](https://openapi-generator.tech/docs/generators)."
            ],
            'servers'      => [
                [
                    'url' => '/api/v1'
                ]
            ],
            'tags'         => [
                ['name' => 'App']
            ],
            'paths'        => [
                '/App/user' => [
                    'get' => [
                        'tags'        => ['App'],
                        "summary"     => "Generate authorization token and return authorized user data.",
                        "description" => "Generate authorization token and return authorized user data.",
                        "operationId" => "getAuthorizedUserData",
                        'security'    => [['basicAuth' => []]],
                        'parameters'  => [
                            [
                                "name"     => "Authorization-Token-Only",
                                "in"       => "header",
                                "required" => true,
                                "schema"   => [
                                    "type"    => "boolean",
                                    "example" => "true"
                                ]
                            ],
                            [
                                "name"        => "Authorization-Token-Lifetime",
                                "description" => 'qwe 123',
                                "in"          => "header",
                                "required"    => false,
                                "schema"      => [
                                    "type"    => "integer",
                                    "example" => "0"
                                ]
                            ],
                            [
                                "name"     => "Authorization-Token-Idletime",
                                "in"       => "header",
                                "required" => false,
                                "schema"   => [
                                    "type"    => "integer",
                                    "example" => "0"
                                ]
                            ],
                        ],
                        "responses"   => [
                            "200" => [
                                "description" => "OK",
                                "content"     => [
                                    "application/json" => [
                                        "schema" => [
                                            "type"       => "object",
                                            "properties" => [
                                                "authorizationToken" => [
                                                    "type" => "string",
                                                ],
                                            ]
                                        ]
                                    ]
                                ]
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
                        ]
                    ]
                ]
            ],
            'components'   => [
                'securitySchemes' => [
                    'basicAuth'           => [
                        'type'   => 'http',
                        'scheme' => 'basic',
                    ],
                    'Authorization-Token' => [
                        'type' => 'apiKey',
                        'name' => 'Authorization-Token',
                        'in'   => 'header'
                    ]
                ]
            ],
            'externalDocs' => [
                'description' => 'How to authorize?',
                'url'         => 'https://github.com/atrocore/atrocore-docs/blob/master/en/developer-guide/rest-api.md'
            ]
        ];

        /** @var Metadata $metadata */
        $metadata = $this->container->get('metadata');

        foreach ($metadata->get(['entityDefs'], []) as $entityName => $data) {
            if (empty($data['fields'])) {
                continue;
            }

            $result['components']['schemas'][$entityName] = [
                'type'       => 'object',
                'properties' => [
                    'id'      => ['type' => 'string'],
                    'deleted' => ['type' => 'boolean'],
                ],
            ];

            foreach ($data['fields'] as $fieldName => $fieldData) {
                if (!empty($fieldData['noLoad']) || !empty($fieldData['emHidden'])) {
                    continue 1;
                }

                if (!empty($fieldData['required'])) {
                    if (empty($result['components']['schemas'][$entityName]['required'])) {
                        $result['components']['schemas'][$entityName]['required'] = [];
                    }
                    $result['components']['schemas'][$entityName]['required'][] = $fieldName;
                }

                switch ($fieldData['type']) {
                    case "autoincrement":
                    case "int":
                        $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'integer'];
                        break;
                    case "bool":
                        $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'boolean'];
                        break;
                    case "jsonArray":
                    case "jsonObject":
                        $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'object'];
                        break;
                    case "currency":
                        $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'string'];
                        $result['components']['schemas'][$entityName]['properties']["{$fieldName}Currency"] = ['type' => 'string'];
                        break;
                    case "unit":
                        $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'string'];
                        $result['components']['schemas'][$entityName]['properties']["{$fieldName}Unit"] = ['type' => 'string'];
                        break;
                    case "array":
                    case "multiEnum":
                        $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'array', 'items' => ['type' => 'string']];
                        break;
                    case "asset":
                    case "file":
                    case "image":
                    case "link":
                    case "linkParent":
                        $result['components']['schemas'][$entityName]['properties']["{$fieldName}Id"] = ['type' => 'string'];
                        $result['components']['schemas'][$entityName]['properties']["{$fieldName}Name"] = ['type' => 'string'];
                        break;
                    case "linkMultiple":
                        $result['components']['schemas'][$entityName]['properties']["{$fieldName}Ids"] = ['type' => 'array', 'items' => ['type' => 'string']];
                        $result['components']['schemas'][$entityName]['properties']["{$fieldName}Names"] = ['type' => 'object'];
                        break;
                    default:
                        $result['components']['schemas'][$entityName]['properties'][$fieldName] = ['type' => 'string'];
                }
            }
            $schemas[$entityName] = $result['components']['schemas'][$entityName];
        }

        foreach ($metadata->get(['scopes'], []) as $scopeName => $scopeData) {
            if (!isset($result['components']['schemas'][$scopeName])) {
                continue 1;
            }

            $result['tags'][] = ['name' => $scopeName];

            // prepare schema data
            $schema = null;
            if (isset($schemas[$scopeName])) {
                $schema = $schemas[$scopeName];
                unset($schema['properties']['id']);
                unset($schema['properties']['deleted']);

                foreach ($schema['properties'] as $k => $v) {
                    if (
                        substr($k, 0, 1) === '_'
                        || substr($k, -4) === 'Name'
                        || substr($k, -5) === 'Names'
                        || $k === 'createdAt'
                        || $k === 'modifiedAt'
                        || $k === 'createdById'
                    ) {
                        unset($schema['properties'][$k]);
                    }
                }
            }

            $result['paths']["/{$scopeName}"]['get'] = [
                'tags'        => [$scopeName],
                "summary"     => "Returns a collection of $scopeName records",
                "description" => "Returns a collection of $scopeName records",
                "operationId" => "getListOf{$scopeName}Items",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "select",
                        "in"       => "query",
                        "required" => false,
                        "schema"   => [
                            "type"    => "string",
                            "example" => "name,createdAt"
                        ]
                    ],
                    [
                        "name"     => "offset",
                        "in"       => "query",
                        "required" => false,
                        "schema"   => [
                            "type"    => "integer",
                            "example" => 0
                        ]
                    ],
                    [
                        "name"     => "maxSize",
                        "in"       => "query",
                        "required" => false,
                        "schema"   => [
                            "type"    => "integer",
                            "example" => 50
                        ]
                    ],
                    [
                        "name"     => "sortBy",
                        "in"       => "query",
                        "required" => false,
                        "schema"   => [
                            "type"    => "string",
                            "example" => "name"
                        ]
                    ],
                    [
                        "name"     => "asc",
                        "in"       => "query",
                        "required" => false,
                        "schema"   => [
                            "type"    => "boolean",
                            "example" => "true"
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses([
                    "type"       => "object",
                    "properties" => [
                        "total" => [
                            "type" => "integer"
                        ],
                        "list"  => [
                            "type"  => "array",
                            "items" => [
                                '$ref' => "#/components/schemas/$scopeName"
                            ]
                        ],
                    ]
                ]),
            ];

            $result['paths']["/{$scopeName}/{id}"]['get'] = [
                'tags'        => [$scopeName],
                "summary"     => "Returns a record of the $scopeName",
                "description" => "Returns a record of the $scopeName",
                "operationId" => "get{$scopeName}Item",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "id",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses(['$ref' => "#/components/schemas/$scopeName"])
            ];

            $result['paths']["/{$scopeName}"]['post'] = [
                'tags'        => [$scopeName],
                "summary"     => "Create a record of the $scopeName",
                "description" => "Create a record of the $scopeName",
                "operationId" => "create{$scopeName}Item",
                'security'    => [['Authorization-Token' => []]],
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'application/json' => [
                            'schema' => $schema
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses(['$ref' => "#/components/schemas/$scopeName"])
            ];

            $result['paths']["/{$scopeName}/{id}"]['put'] = [
                'tags'        => [$scopeName],
                "summary"     => "Update a record of the $scopeName",
                "description" => "Update a record of the $scopeName",
                "operationId" => "update{$scopeName}Item",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "id",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                ],
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'application/json' => [
                            'schema' => $schema
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses(['$ref' => "#/components/schemas/$scopeName"])
            ];

            $result['paths']["/{$scopeName}/{id}"]['delete'] = [
                'tags'        => [$scopeName],
                "summary"     => "Delete a record of the $scopeName",
                "description" => "Delete a record of the $scopeName",
                "operationId" => "delete{$scopeName}Item",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "id",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses(['type' => 'boolean'])
            ];

            $result['paths']["/{$scopeName}/{id}/{link}"]['get'] = [
                'tags'        => [$scopeName],
                "summary"     => "Returns linked entities for the $scopeName",
                "description" => "Returns linked entities for the $scopeName",
                "operationId" => "getLinkedItemsFor{$scopeName}Item",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "id",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                    [
                        "name"     => "link",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses([
                    "type"       => "object",
                    "properties" => [
                        "total" => [
                            "type" => "integer"
                        ],
                        "list"  => [
                            "type"  => "array",
                            "items" => [
                                "type" => "object"
                            ]
                        ],
                    ]
                ]),
            ];

            $result['paths']["/{$scopeName}/action/massUpdate"]['put'] = [
                'tags'        => [$scopeName],
                "summary"     => "Mass update of $scopeName data",
                "description" => "Mass update of $scopeName data",
                "operationId" => "massUpdate{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'application/json' => [
                            'schema' => [
                                "type"       => "object",
                                "properties" => [
                                    "attributes" => [
                                        "type"    => "object",
                                        'example' => ['name' => 'New name', 'description' => 'New description']
                                    ],
                                    "ids"        => [
                                        "type"    => "array",
                                        "items"   => [
                                            "type" => "string"
                                        ],
                                        'example' => ["613219736ca7a1c68", "6132197390d69afa5"]
                                    ],
                                ],
                            ]
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses(['type' => 'boolean'])
            ];

            $result['paths']["/{$scopeName}/action/massDelete"]['post'] = [
                'tags'        => [$scopeName],
                "summary"     => "Mass delete of $scopeName data",
                "description" => "Mass delete of $scopeName data",
                "operationId" => "massDelete{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'application/json' => [
                            'schema' => [
                                "type"       => "object",
                                "properties" => [
                                    "ids" => [
                                        "type"    => "array",
                                        "items"   => [
                                            "type" => "string"
                                        ],
                                        'example' => ["613219736ca7a1c68", "6132197390d69afa5"]
                                    ],
                                ],
                            ]
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses(['type' => 'boolean'])
            ];

            $result['paths']["/{$scopeName}/{id}/{link}"]['post'] = [
                'tags'        => [$scopeName],
                "summary"     => "Link $scopeName to Entities",
                "description" => "Link $scopeName to Entities",
                "operationId" => "link{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "id",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                    [
                        "name"     => "link",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                ],
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'application/json' => [
                            'schema' => [
                                "type"       => "object",
                                "properties" => [
                                    "ids" => [
                                        "type"    => "array",
                                        "items"   => [
                                            "type" => "string"
                                        ],
                                        'example' => ["613219736ca7a1c68", "6132197390d69afa5"]
                                    ],
                                ],
                            ]
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses(['type' => 'boolean'])
            ];

            $result['paths']["/{$scopeName}/{id}/{link}"]['delete'] = [
                'tags'        => [$scopeName],
                "summary"     => "Unlink $scopeName from Entities",
                "description" => "Unlink $scopeName from Entities",
                "operationId" => "unlink{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "id",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                    [
                        "name"     => "link",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                ],
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'application/json' => [
                            'schema' => [
                                "type"       => "object",
                                "properties" => [
                                    "ids" => [
                                        "type"    => "array",
                                        "items"   => [
                                            "type" => "string"
                                        ],
                                        'example' => ["613219736ca7a1c68", "6132197390d69afa5"]
                                    ],
                                ],
                            ]
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses(['type' => 'boolean'])
            ];

            $result['paths']["/{$scopeName}/{id}/subscription"]['put'] = [
                'tags'        => [$scopeName],
                "summary"     => "Follow the $scopeName stream",
                "description" => "Follow the $scopeName stream",
                "operationId" => "follow{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "id",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ]
                ],
                "responses"   => $this->prepareResponses(['type' => 'boolean'])
            ];

            $result['paths']["/{$scopeName}/{id}/subscription"]['delete'] = [
                'tags'        => [$scopeName],
                "summary"     => "Unfollow the $scopeName stream",
                "description" => "Unfollow the $scopeName stream",
                "operationId" => "unfollow{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "id",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ]
                ],
                "responses"   => $this->prepareResponses(['type' => 'boolean'])
            ];

            $result['paths']["/{$scopeName}/action/getDuplicateAttributes"]['post'] = [
                'tags'        => [$scopeName],
                "summary"     => "Get duplicate attributes from $scopeName",
                "description" => "Get duplicate attributes from $scopeName",
                "operationId" => "getDuplicateAttributes{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'application/json' => [
                            'schema' => [
                                "type"       => "object",
                                "properties" => [
                                    "id" => [
                                        "type" => "string"
                                    ],
                                ],
                            ]
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses($schema)
            ];

            $result['paths']["/{$scopeName}/action/massFollow"]['post'] = [
                'tags'        => [$scopeName],
                "summary"     => "Mass follow to $scopeName records",
                "description" => "Mass follow to $scopeName records",
                "operationId" => "massFollow{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'application/json' => [
                            'schema' => [
                                "type"       => "object",
                                "properties" => [
                                    "ids" => [
                                        "type"    => "array",
                                        "items"   => [
                                            "type" => "string"
                                        ],
                                        'example' => ["613219736ca7a1c68", "6132197390d69afa5"]
                                    ],
                                ],
                            ]
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses([
                    "type"       => "object",
                    "properties" => [
                        "ids"   => [
                            "type"    => "array",
                            "items"   => [
                                "type" => "string"
                            ],
                            'example' => ["613219736ca7a1c68", "6132197390d69afa5"]
                        ],
                        "count" => [
                            "type" => "integer",
                        ]
                    ],
                ])
            ];

            $result['paths']["/{$scopeName}/action/massUnfollow"]['post'] = [
                'tags'        => [$scopeName],
                "summary"     => "Mass unfollow from $scopeName records",
                "description" => "Mass unfollow from $scopeName records",
                "operationId" => "massUnfollow{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'application/json' => [
                            'schema' => [
                                "type"       => "object",
                                "properties" => [
                                    "ids" => [
                                        "type"    => "array",
                                        "items"   => [
                                            "type" => "string"
                                        ],
                                        'example' => ["613219736ca7a1c68", "6132197390d69afa5"]
                                    ],
                                ],
                            ]
                        ]
                    ],
                ],
                "responses"   => $this->prepareResponses([
                    "type"       => "object",
                    "properties" => [
                        "ids"   => [
                            "type"    => "array",
                            "items"   => [
                                "type" => "string"
                            ],
                            'example' => ["613219736ca7a1c68", "6132197390d69afa5"]
                        ],
                        "count" => [
                            "type" => "integer",
                        ]
                    ],
                ])
            ];
        }

        $this->pushComposerActions($result, $schemas);

        foreach ($this->container->get('moduleManager')->getModules() as $module) {
            $module->prepareApiDocs($result);
        }

        return $result;
    }

    protected function prepareResponses(array $success): array
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

    protected function pushComposerActions(array &$result, array $schemas): void
    {
        $result['tags'][] = ['name' => 'Composer'];

        $result['paths']["/Composer/runUpdate"]['post'] = [
            'tags'        => ['Composer'],
            "summary"     => "Run update",
            "description" => "Run update",
            "operationId" => "runUpdateComposer",
            'security'    => [['Authorization-Token' => []]],
            "responses"   => $this->prepareResponses(['type' => 'boolean'])
        ];

        $result['paths']["/Composer/cancelUpdate"]['delete'] = [
            'tags'        => ['Composer'],
            "summary"     => "Cancel changes",
            "description" => "Cancel changes",
            "operationId" => "cancelUpdateComposer",
            'security'    => [['Authorization-Token' => []]],
            "responses"   => $this->prepareResponses(['type' => 'boolean'])
        ];

        $result['paths']["/Composer/list"]['get'] = [
            'tags'        => ['Composer'],
            "summary"     => "Get installed modules",
            "description" => "Get installed modules",
            "operationId" => "getInstalledModules",
            'security'    => [['Authorization-Token' => []]],
            'parameters'  => [
                [
                    "name"     => "select",
                    "in"       => "query",
                    "required" => false,
                    "schema"   => [
                        "type"    => "string",
                        "example" => "name,createdAt"
                    ]
                ],
                [
                    "name"     => "offset",
                    "in"       => "query",
                    "required" => false,
                    "schema"   => [
                        "type"    => "integer",
                        "example" => 0
                    ]
                ],
                [
                    "name"     => "maxSize",
                    "in"       => "query",
                    "required" => false,
                    "schema"   => [
                        "type"    => "integer",
                        "example" => 50
                    ]
                ],
                [
                    "name"     => "sortBy",
                    "in"       => "query",
                    "required" => false,
                    "schema"   => [
                        "type"    => "string",
                        "example" => "name"
                    ]
                ],
                [
                    "name"     => "asc",
                    "in"       => "query",
                    "required" => false,
                    "schema"   => [
                        "type"    => "boolean",
                        "example" => "true"
                    ]
                ],
            ],
            "responses"   => $this->prepareResponses([
                "type"       => "object",
                "properties" => [
                    "total" => [
                        "type" => "integer"
                    ],
                    "list"  => [
                        "type"  => "array",
                        "items" => [
                            "type"       => "object",
                            "properties" => [
                                "id"             => ["type" => "string"],
                                "description"    => ["type" => "string"],
                                "currentVersion" => ["type" => "string"],
                                "status"         => ["type" => "string"],
                                "isSystem"       => ["type" => "boolean"],
                            ]
                        ]
                    ],
                ]
            ]),
        ];

        $result['paths']["/Composer/installModule"]['post'] = [
            'tags'        => ['Composer'],
            "summary"     => "Install module",
            "description" => "Install module",
            "operationId" => "installModule",
            'security'    => [['Authorization-Token' => []]],
            'requestBody' => [
                'required' => true,
                'content'  => [
                    'application/json' => [
                        'schema' => [
                            "type"       => "object",
                            "properties" => [
                                "id" => [
                                    "type" => "string",
                                ],
                            ],
                        ]
                    ]
                ],
            ],
            "responses"   => $this->prepareResponses(['type' => 'boolean'])
        ];

        $result['paths']["/Composer/deleteModule"]['delete'] = [
            'tags'        => ['Composer'],
            "summary"     => "Delete module",
            "description" => "Delete module",
            "operationId" => "deleteModule",
            'security'    => [['Authorization-Token' => []]],
            'requestBody' => [
                'required' => true,
                'content'  => [
                    'application/json' => [
                        'schema' => [
                            "type"       => "object",
                            "properties" => [
                                "id" => [
                                    "type" => "string",
                                ],
                            ],
                        ]
                    ]
                ],
            ],
            "responses"   => $this->prepareResponses(['type' => 'boolean'])
        ];

        $result['paths']["/Composer/cancel"]['post'] = [
            'tags'        => ['Composer'],
            "summary"     => "Cancel module changes",
            "description" => "Cancel module changes",
            "operationId" => "cancelModule",
            'security'    => [['Authorization-Token' => []]],
            'requestBody' => [
                'required' => true,
                'content'  => [
                    'application/json' => [
                        'schema' => [
                            "type"       => "object",
                            "properties" => [
                                "id" => [
                                    "type" => "string",
                                ],
                            ],
                        ]
                    ]
                ],
            ],
            "responses"   => $this->prepareResponses(['type' => 'boolean'])
        ];

        $result['paths']["/Composer/logs"]['get'] = [
            'tags'        => ['Composer'],
            "summary"     => "Get updates logs",
            "description" => "Get updates logs",
            "operationId" => "getModulesLogs",
            'security'    => [['Authorization-Token' => []]],
            'parameters'  => [
                [
                    "name"     => "select",
                    "in"       => "query",
                    "required" => false,
                    "schema"   => [
                        "type"    => "string",
                        "example" => "name,createdAt"
                    ]
                ],
                [
                    "name"     => "offset",
                    "in"       => "query",
                    "required" => false,
                    "schema"   => [
                        "type"    => "integer",
                        "example" => 0
                    ]
                ],
                [
                    "name"     => "maxSize",
                    "in"       => "query",
                    "required" => false,
                    "schema"   => [
                        "type"    => "integer",
                        "example" => 50
                    ]
                ],
                [
                    "name"     => "sortBy",
                    "in"       => "query",
                    "required" => false,
                    "schema"   => [
                        "type"    => "string",
                        "example" => "name"
                    ]
                ],
                [
                    "name"     => "asc",
                    "in"       => "query",
                    "required" => false,
                    "schema"   => [
                        "type"    => "boolean",
                        "example" => "true"
                    ]
                ],
            ],
            "responses"   => $this->prepareResponses([
                "type"       => "object",
                "properties" => [
                    "total" => [
                        "type" => "integer"
                    ],
                    "list"  => [
                        "type"  => "array",
                        "items" => $schemas['Note']
                    ],
                ]
            ]),
        ];
    }
}
