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

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Atro\Services\Composer;

class OpenApiGenerator
{
    private const HEADER_LANGUAGE_DESCRIPTION = "Set this parameter for data to be returned for a specified language";

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

    public function getFullSchema(): array
    {
        $result = $this->getBase();

        foreach ($this->container->get('route')->getAll() as $route) {
            $this->pushRoute($result, $route);
        }

        /** @var Metadata $metadata */
        $metadata = $this->container->get('metadata');

        /** @var Config $config */
        $config = $this->container->get('config');

        $languages = [];
        if (!empty($config->get('isMultilangActive'))) {
            $languages = $config->get('inputLanguageList', []);
        }

        foreach ($metadata->get(['entityDefs'], []) as $entityName => $data) {
            $scopeData = $metadata->get(['scopes', $entityName]);

            if (empty($data['fields']) || !empty($scopeData['emHidden'])) {
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
                if (!empty($fieldData['noLoad']) || (!empty($fieldData['notStorable']) && empty($fieldData['dataField']))) {
                    continue;
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
                        $result['components']['schemas'][$entityName]['properties']["{$fieldName}Name"] = [
                            'type'    => 'string',
                            'forRead' => true
                        ];
                        break;
                    case "linkMultiple":
                        $result['components']['schemas'][$entityName]['properties']["{$fieldName}Ids"] = [
                            'type'  => 'array',
                            'items' => ['type' => 'string']
                        ];
                        $result['components']['schemas'][$entityName]['properties']["{$fieldName}Names"] = [
                            'type'    => 'object',
                            'forRead' => true
                        ];
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

            if (empty(ControllerManager::getControllerClassName($scopeName, $this->container->get('metadata')))) {
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
                        || $k === 'createdAt'
                        || $k === 'modifiedAt'
                        || $k === 'createdById'
                        || !empty($v['forRead'])
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
                        "name"        => "language",
                        "in"          => "header",
                        "required"    => false,
                        "description" => self::HEADER_LANGUAGE_DESCRIPTION,
                        "schema"      => [
                            "type" => "string",
                            "enum" => $languages,
                        ]
                    ],
                    [
                        "name"        => "select",
                        "in"          => "query",
                        "required"    => false,
                        "description" => "Fields according to $scopeName metadata. For example: id, name, createdAt, ...",
                        "schema"      => [
                            "type"    => "string",
                            "example" => "name,createdAt"
                        ]
                    ],
                    [
                        "name"        => "where",
                        "in"          => "query",
                        "required"    => false,
                        "description" => "There are a lot of filter types supported. You can learn all of them if you trace what's requested by Atro UI in the network tab in your browser console (press F12 key to open the console).",
                        "content"     => [
                            "application/json" => [
                                "schema" => [
                                    "type"    => "array",
                                    "items"   => [
                                        "type" => "object",
                                    ],
                                    'example' => [
                                        [
                                            'type'  => 'or',
                                            'value' => [
                                                ['type' => 'like', 'attribute' => 'name', 'value' => '%find-me-1%'],
                                                ['type' => 'equals', 'attribute' => 'name', 'value' => 'find-me-2']
                                            ]
                                        ]
                                    ]
                                ],
                            ],
                        ],
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
                "responses"   => self::prepareResponses([
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
                        "name"        => "language",
                        "in"          => "header",
                        "required"    => false,
                        "description" => self::HEADER_LANGUAGE_DESCRIPTION,
                        "schema"      => [
                            "type" => "string",
                            "enum" => $languages,
                        ]
                    ],
                    [
                        "name"     => "id",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                ],
                "responses"   => self::prepareResponses(['$ref' => "#/components/schemas/$scopeName"])
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
                "responses"   => self::prepareResponses(['$ref' => "#/components/schemas/$scopeName"])
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
                "responses"   => self::prepareResponses(['$ref' => "#/components/schemas/$scopeName"])
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
                    [
                        "name"        => "permanently",
                        "in"          => "header",
                        "required"    => false,
                        "description" => "Set to TRUE if you want to delete the record permanently",
                        "schema"      => [
                            "type"    => "boolean",
                            "example" => false,
                        ]
                    ],
                ],
                "responses"   => self::prepareResponses(['type' => 'boolean'])
            ];

            $result['paths']["/{$scopeName}/{id}/{link}"]['get'] = [
                'tags'        => [$scopeName],
                "summary"     => "Returns linked entities for the $scopeName",
                "description" => "Returns linked entities for the $scopeName",
                "operationId" => "getLinkedItemsFor{$scopeName}Item",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"        => "language",
                        "in"          => "header",
                        "required"    => false,
                        "description" => self::HEADER_LANGUAGE_DESCRIPTION,
                        "schema"      => [
                            "type" => "string",
                            "enum" => $languages,
                        ]
                    ],
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
                "responses"   => self::prepareResponses([
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
                "responses"   => self::prepareResponses(['type' => 'boolean'])
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
                                    "ids"         => [
                                        "type"    => "array",
                                        "items"   => [
                                            "type" => "string"
                                        ],
                                        'example' => ["613219736ca7a1c68", "6132197390d69afa5"]
                                    ],
                                    "permanently" => [
                                        "type"    => "boolean",
                                        'example' => false
                                    ],
                                ],
                            ]
                        ]
                    ],
                ],
                "responses"   => self::prepareResponses(['type' => 'boolean'])
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
                "responses"   => self::prepareResponses(['type' => 'boolean'])
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
                    [
                        "name"     => "ids",
                        "in"       => "query",
                        "required" => true,
                        "explode"  => false,
                        "schema"   => [
                            "type"  => "array",
                            "items" => [
                                "type" => "string"
                            ]
                        ]
                    ],
                ],
                "responses"   => self::prepareResponses(['type' => 'boolean'])
            ];

            $result['paths']["/{$scopeName}/{link}/relation"]['post'] = [
                'tags'        => [$scopeName],
                "summary"     => "Add relation for $scopeName",
                "description" => "Add relation for $scopeName",
                "operationId" => "addRelationFor{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "link",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                    [
                        "name"     => "ids",
                        "in"       => "query",
                        "required" => true,
                        "explode"  => false,
                        "schema"   => [
                            "type"  => "array",
                            "items" => [
                                "type" => "string",
                            ],
                        ]
                    ],
                    [
                        "name"     => "foreignIds",
                        "in"       => "query",
                        "required" => true,
                        "explode"  => false,
                        "schema"   => [
                            "type"  => "array",
                            "items" => [
                                "type" => "string",
                            ],
                        ]
                    ]
                ],
                "responses"   => self::prepareResponses(['type' => 'boolean'])
            ];

            $result['paths']["/{$scopeName}/{link}/relation"]['delete'] = [
                'tags'        => [$scopeName],
                "summary"     => "Remove relation for $scopeName",
                "description" => "Remove relation for $scopeName",
                "operationId" => "removeRelationFor{$scopeName}",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"     => "link",
                        "in"       => "path",
                        "required" => true,
                        "schema"   => [
                            "type" => "string"
                        ]
                    ],
                    [
                        "name"     => "ids",
                        "in"       => "query",
                        "required" => true,
                        "explode"  => false,
                        "schema"   => [
                            "type"  => "array",
                            "items" => [
                                "type" => "string",
                            ],
                        ]
                    ],
                    [
                        "name"     => "foreignIds",
                        "in"       => "query",
                        "required" => true,
                        "explode"  => false,
                        "schema"   => [
                            "type"  => "array",
                            "items" => [
                                "type" => "string",
                            ],
                        ]
                    ]
                ],
                "responses"   => self::prepareResponses(['type' => 'boolean'])
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
                "responses"   => self::prepareResponses([
                    "type"       => "object",
                    "properties" => [
                        "message" => [
                            "type" => "string"
                        ]
                    ]
                ]),
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
                "responses"   => self::prepareResponses(['type' => 'boolean'])
            ];
        }

        $this->pushComposerActions($result, $schemas);
        $this->pushDashletActions($result, $schemas);

        foreach ($this->container->get('moduleManager')->getModules() as $module) {
            $module->prepareApiDocs($result, $schemas);
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

    protected function pushComposerActions(array &$result, array $schemas): void
    {
        $result['tags'][] = ['name' => 'Composer'];

        $result['paths']["/Composer/runUpdate"]['post'] = [
            'tags'        => ['Composer'],
            "summary"     => "Run update",
            "description" => "Run update",
            "operationId" => "runUpdateComposer",
            'security'    => [['Authorization-Token' => []]],
            "responses"   => self::prepareResponses(['type' => 'boolean'])
        ];

        $result['paths']["/Composer/cancelUpdate"]['delete'] = [
            'tags'        => ['Composer'],
            "summary"     => "Cancel changes",
            "description" => "Cancel changes",
            "operationId" => "cancelUpdateComposer",
            'security'    => [['Authorization-Token' => []]],
            "responses"   => self::prepareResponses(['type' => 'boolean'])
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
            "responses"   => self::prepareResponses([
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
                                "isComposer"     => ["type" => "boolean"],
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
            "responses"   => self::prepareResponses(['type' => 'boolean'])
        ];

        $result['paths']["/Composer/deleteModule"]['delete'] = [
            'tags'        => ['Composer'],
            "summary"     => "Delete module",
            "description" => "Delete module",
            "operationId" => "deleteModule",
            'security'    => [['Authorization-Token' => []]],
            'parameters'  => [
                [
                    "name"     => "id",
                    "in"       => "query",
                    "required" => true,
                    "schema"   => [
                        "type" => "string"
                    ]
                ],
            ],
            "responses"   => self::prepareResponses(['type' => 'boolean'])
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
            "responses"   => self::prepareResponses(['type' => 'boolean'])
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
            "responses"   => self::prepareResponses([
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

    protected function pushDashletActions(array &$result, array $schemas): void
    {
        $result['tags'][] = ['name' => 'Dashlet'];

        $result['paths']["/Dashlet/{dashletName}"]['get'] = [
            'tags'        => ['Dashlet'],
            "summary"     => "Get Dashlet data",
            "description" => "Get Dashlet data",
            "operationId" => "getDashletData",
            'security'    => [['Authorization-Token' => []]],
            'parameters'  => [
                [
                    "name"     => "dashletName",
                    "in"       => "path",
                    "required" => true,
                    "schema"   => [
                        "type" => "string"
                    ]
                ]
            ],
            "responses"   => self::prepareResponses([
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
    }

    protected function getBase(): array
    {
        return [
            'openapi'    => '3.0.0',
            'info'       => [
                'version'     => Composer::getCoreVersion(),
                'title'       => 'AtroCore REST API documentation',
                'description' => "This is a REST API documentation for AtroCore data platform and its modules (AtroPIM, AtroDAM and others), which is based on [OpenAPI (Swagger) Specification](https://swagger.io/specification/). You can generate your client [here](https://openapi-generator.tech/docs/generators).<br><br><h3>Video tutorials:</h3><ul><li>[How to authorize?](https://youtu.be/GWfNRvCswXg)</li><li>[How to select specific fields?](https://youtu.be/i7o0aENuyuY)</li><li>[How to filter data records?](https://youtu.be/irgWkN4wlkM)</li></ul>"
            ],
            'servers'    => [
                [
                    'url' => '/api/v1'
                ]
            ],
            'tags'       => [
                ['name' => 'App']
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
                    ]
                ]
            ]
        ];
    }

    protected function pushRoute(array &$result, array $route): void
    {
        if (!empty($route['description'])) {
            $row = [
                'tags'        => [$route['params']['controller']],
                'summary'     => $route['summary'] ?? $route['description'],
                'description' => $route['description'],
                'operationId' => md5("{$route['route']}_{$route['method']}"),
                "responses"   => self::prepareResponses($route['response'])
            ];

            if (!isset($route['conditions']['auth']) || $route['conditions']['auth'] !== false) {
                $row['security'] = [['Authorization-Token' => []]];
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

            $result['paths'][$route['route']][$route['method']] = $row;
        }
    }
}
