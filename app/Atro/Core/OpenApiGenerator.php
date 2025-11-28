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

use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Services\Composer;
use Atro\Core\Utils\Util;
use Espo\ORM\EntityManager;

class OpenApiGenerator
{
    private const HEADER_LANGUAGE_DESCRIPTION = "Set this parameter for data to be returned for a specified language";

    private const TIMEZONE_DESCRIPTION = "Specify if you need to get dates in a certain time zone. By default, dates are returned in the UTC time zone.";

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

        /** @var Config $config */
        $config = $this->container->get('config');

        $languageParam = null;
        if (!empty($config->get('isMultilangActive')) && !empty($languages = $config->get('inputLanguageList', []))) {
            $languageParam = [
                "name"        => "language",
                "in"          => "header",
                "required"    => false,
                "description" => self::HEADER_LANGUAGE_DESCRIPTION,
                "schema"      => [
                    "type" => "string",
                    "enum" => $languages,
                ],
            ];
        }

        foreach ($this->getMetadata()->get(['entityDefs'], []) as $entityName => $data) {
            $scopeData = $this->getMetadata()->get(['scopes', $entityName]);

            if (empty($data['fields']) || empty($scopeData['entity']) || !empty($scopeData['openApiHidden'])) {
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
                $this->getFieldSchema($result, $entityName, $fieldName, $fieldData);
            }

            if ($this->getMetadata()->get(['scopes', $entityName, 'hasAttribute'])) {
                $attributes = $this->getEntityManager()->getRepository('Attribute')->getEntityAttributes($entityName);
                foreach ($attributes as $attribute) {
                    $this->getFieldSchema($result, $entityName, AttributeFieldConverter::prepareFieldName($attribute), ['type' => $attribute['type'], 'outputType' => $attribute['output_type'] ?? null]);
                }
                $result['components']['schemas'][$entityName]['properties']['attributesDefs'] = ["type" => "object", "forRead" => true];
            }

            $schemas[$entityName] = $result['components']['schemas'][$entityName];
        }

        foreach ($this->getMetadata()->get(['scopes'], []) as $scopeName => $scopeData) {
            if (!isset($result['components']['schemas'][$scopeName])) {
                continue 1;
            }

            if (empty(ControllerManager::getControllerClassName($scopeName, $this->getMetadata()))) {
                continue 1;
            }

            $result['tags'][] = ['name' => $scopeName];

            // prepare schema data
            $schema = null;
            if (isset($schemas[$scopeName])) {
                $schema = $schemas[$scopeName];
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
                        "name"        => "timezone",
                        "in"          => "query",
                        "required"    => false,
                        "description" => self::TIMEZONE_DESCRIPTION,
                        "schema"      => [
                            "type"    => "string",
                            "example" => "Europe/Berlin"
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

            if (!empty($languageParam)) {
                $result['paths']["/{$scopeName}"]['get']['parameters'][] = $languageParam;
            }

            if($this->getMetadata()->get(['scopes', $scopeName, 'hasAttribute'])) {
                $result['paths']["/{$scopeName}"]['get']['parameters'][] =   [
                    "name"        => "attributes",
                    "in"          => "query",
                    "required"    => false,
                    "description" => "Attributes Ids according to $scopeName attributes",
                    "schema"      => [
                        "type"    => "string",
                        "example" => "a01k4pyczndebktndtacfv3055c,a01k4pwfb59e76tcrndz11n7s6b"
                    ]
                ];

                $result['paths']["/{$scopeName}"]['get']['parameters'][] =   [
                    "name"        => "allAttributes",
                    "in"          => "query",
                    "required"    => false,
                    "description" => "Load data with all the existing attributes for $scopeName",
                    "schema"      => [
                        "type"    => "boolean",
                        "example" => "false"
                    ]
                ];
            }

            $result['paths']["/{$scopeName}/{id}"]['get'] = [
                'tags'        => [$scopeName],
                "summary"     => "Returns a record of the $scopeName",
                "description" => "Returns a record of the $scopeName",
                "operationId" => "get{$scopeName}Item",
                'security'    => [['Authorization-Token' => []]],
                'parameters'  => [
                    [
                        "name"        => "timezone",
                        "in"          => "query",
                        "required"    => false,
                        "description" => self::TIMEZONE_DESCRIPTION,
                        "schema"      => [
                            "type"    => "string",
                            "example" => "Europe/Berlin"
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

            if (!empty($languageParam)) {
                $result['paths']["/{$scopeName}/{id}"]['get']['parameters'][] = $languageParam;
            }

            if (!empty($scopeData['type']) && $scopeData['type'] !== 'Archive' && $scopeName !== 'MatchedRecord') {
                if (!in_array($scopeName, ['Matching', 'MasterDataEntity'])) {
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
                                    'schema' => $schema,
                                ],
                            ],
                        ],
                        "responses"   => self::prepareResponses(['$ref' => "#/components/schemas/$scopeName"]),
                    ];
                }

                $putSchema = $schema;
                unset($putSchema['properties']['id']);

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
                                "type" => "string",
                            ],
                        ],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content'  => [
                            'application/json' => [
                                'schema' => $putSchema,
                            ],
                        ],
                    ],
                    "responses"   => self::prepareResponses(['$ref' => "#/components/schemas/$scopeName"]),
                ];

                if (!in_array($scopeName, ['Matching', 'MasterDataEntity'])) {
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
                                    "type" => "string",
                                ],
                            ],
                            [
                                "name"        => "permanently",
                                "in"          => "header",
                                "required"    => false,
                                "description" => "Set to TRUE if you want to delete the record permanently",
                                "schema"      => [
                                    "type"    => "boolean",
                                    "example" => false,
                                ],
                            ],
                        ],
                        "responses"   => self::prepareResponses(['type' => 'boolean']),
                    ];
                }
            }

            if (!empty($scopeData['type']) && !in_array($scopeData['type'], ['ReferenceData', 'Archive']) && $scopeName !== 'MatchedRecord') {
                $result['paths']["/{$scopeName}/{id}/{link}"]['get'] = [
                    'tags'        => [$scopeName],
                    "summary"     => "Returns linked entities for the $scopeName",
                    "description" => "Returns linked entities for the $scopeName",
                    "operationId" => "getLinkedItemsFor{$scopeName}Item",
                    'security'    => [['Authorization-Token' => []]],
                    'parameters'  => [
                        [
                            "name"        => "timezone",
                            "in"          => "query",
                            "required"    => false,
                            "description" => self::TIMEZONE_DESCRIPTION,
                            "schema"      => [
                                "type"    => "string",
                                "example" => "Europe/Berlin"
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

                if (!empty($languageParam)) {
                    $result['paths']["/{$scopeName}/{id}/{link}"]['get']['parameters'][] = $languageParam;
                }

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

                if (!empty($scopeData['hasAttribute'])) {
                    $result['paths']["/{$scopeName}/action/massRemoveAttribute"]['post'] = [
                        'tags'        => [$scopeName],
                        "summary"     => "Mass remove attribute on $scopeName record",
                        "description" => "Mass remove attribute on $scopeName record",
                        "operationId" => "massRemove{$scopeName}Attribute",
                        'security'    => [['Authorization-Token' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content'  => [
                                'application/json' => [
                                    'schema' => [
                                        "type"       => "object",
                                        "properties" => [
                                            "attributes" => [
                                                "type"       => "object",
                                                "properties" => [
                                                    "ids" => [
                                                        "type"    => "array",
                                                        "items"   => [
                                                            "type" => "string"
                                                        ],
                                                        'example' => ["613219736ca7a1c68", "6132197390d69afa5"]
                                                    ]
                                                ]
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
                        "responses"   => self::prepareResponses(['type' => 'object'])
                    ];
                }

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
                        ]
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
                                        "foreignIds" => [
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
                        ]
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
                                        "foreignIds" => [
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

                if (empty($this->getMetadata()->get("scopes.$scopeName.streamDisabled"))) {
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
                                    "type" => "string",
                                ],
                            ],
                        ],
                        "responses"   => self::prepareResponses([
                            "type"       => "object",
                            "properties" => [
                                "message" => [
                                    "type" => "string",
                                ],
                            ],
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
                                    "type" => "string",
                                ],
                            ],
                        ],
                        "responses"   => self::prepareResponses(['type' => 'boolean']),
                    ];
                }
            }
        }

        $this->pushComposerActions($result, $schemas);
        $this->pushDashletActions($result, $schemas);

        $this->prepareUserProfileDocs($result, $schemas);

        unset($result['paths']["/ActionLog"]['post']);

        $this->pushUserActions($result, $schemas);
        $this->pushSettingsActions($result, $schemas);
        $this->pushFileActions($result, $schemas);

        foreach ($this->container->get('moduleManager')->getModules() as $module) {
            $module->prepareApiDocs($result, $schemas);
        }

        $this->removeForRead($result);

        return $result;
    }

    protected function removeForRead(array &$array): void
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $this->removeForRead($value);
            }

            if ($key === 'forRead') {
                unset($array[$key]);
            }
        }
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

    protected function prepareUserProfileDocs(array &$result, array $schemas): void
    {
        unset($result['paths']["/UserProfile"]['get']);
        unset($result['paths']["/UserProfile"]['post']);
        unset($result['paths']["/UserProfile/{id}"]['delete']);
        unset($result['paths']["/UserProfile/action/massUpdate"]['put']);
        unset($result['paths']["/UserProfile/action/massDelete"]['post']);
        unset($result['paths']["/UserProfile/{link}/relation"]['post']);
        unset($result['paths']["/UserProfile/{link}/relation"]['delete']);
        unset($result['paths']["/UserProfile/{id}/subscription"]['put']);
        unset($result['paths']["/UserProfile/{id}/subscription"]['delete']);
    }

    protected function pushUserActions(array &$result, array $schemas): void
    {
        if (!isset($result['paths']['/User']['post']['parameters'])) {
            $result['paths']['/User']['post']['parameters'] = [];
        }

        foreach ($schemas['User']['properties'] as $key => $schema) {
            if (in_array($key, ['deleted', 'createdAt', 'modifiedAt', 'createdById']) || !empty($schema['forRead'])) {
                continue;
            }

            switch ($schema['type']) {
                case 'string':
                    $schema['example'] = 'string';
                    break;
                case 'integer':
                    $schema['example'] = '0';
                    break;
                case 'boolean':
                    $schema['example'] = 'false';
                    break;
                case 'array':
                    $schema['example'] = [];
            }

            $result['paths']['/User']['post']['parameters'][] = [
                'name'     => $key,
                'in'       => 'body',
                'schema'   => $schema,
                'required' => !empty($result['components']['schemas']['User']['required']) && in_array($key, $result['components']['schemas']['User']['required'])
            ];
        }
        $result['paths']['/User']['post']['parameters'][] = [
            'name'        => 'passwordConfirm',
            'in'          => 'body',
            'required'    => true,
            'description' => 'Password confirmation. Note: this field in required when there in no "id" key in request body.',
            'schema'      => [
                'type'    => 'string',
                'example' => 'string'
            ]
        ];

        $result['paths']['/User']['post']['requestBody']['content']['application/json']['schema']['properties']['passwordConfirm'] = ['type' => 'string'];
    }

    protected function pushSettingsActions(array &$result, array $schemas): void
    {
        $result['tags'][] = ['name' => 'Settings'];

        foreach ($this->getMetadata()->get(['entityDefs', 'Settings', 'fields']) as $fieldName => $fieldData) {
            $this->getFieldSchema($result, 'Settings', $fieldName, $fieldData);
        }

        $result['paths']['/Settings']['get'] = [
            'tags'        => ['Settings'],
            'in'          => 'body',
            'required'    => true,
            'summary'     => 'Returns a record of Settings',
            'description' => 'Returns a record of Settings',
            'responses'   => self::prepareResponses(['$ref' => '#/components/schemas/Settings'])
        ];

        $result['paths']['/Settings']['patch'] = [
            'tags'        => ['Settings'],
            'in'          => 'body',
            'required'    => true,
            'summary'     => 'Update a record of Settings',
            'description' => 'Update a record of Settings',
            'requestBody' => [
                'required' => true,
                'content'  => [
                    'application/json' => [
                        'schema' => $result['components']['schemas']['Settings']
                    ]
                ],
            ],
            'responses'   => self::prepareResponses(['$ref' => '#/components/schemas/Settings'])
        ];
    }

    protected function pushFileActions(array &$result, array $schemas): void
    {
        $response = self::prepareResponses([]);
        $response['200']['content'] = [
            "application/octet-stream" => [
                'schema' => [
                    'type' => 'string',
                    'format' => 'binary'
                ]
            ]
        ];

        $result['paths']['/File/action/upload-proxy']['post'] = [
            'tags'        => ['File'],
            'summary'     => 'Read file from URL',
            'operationId' => 'uploadProxy',
            'description' => 'Reading the contents of a file provided via a URL link',
            'requestBody' => [
                'required' => true,
                'content'  => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'url' => [
                                    'type' => 'string',
                                    'example' => 'https://your-website.com/image.png'
                                ]
                            ],
                            'required' => ['url']
                        ]
                    ]
                ],
            ],
            'responses' => $response
        ];
    }

    protected function getFieldSchema(array &$result, string $entityName, string $fieldName, array $fieldData)
    {
        if (!empty($fieldData['openApiDisabled'])) {
            return;
        }

        if (empty($fieldData['openApiEnabled'])) {
            if (!empty($fieldData['noLoad']) || (!empty($fieldData['notStorable']) && empty($fieldData['dataField']))) {
                return;
            }
        }

        if($fieldData['type'] === 'script') {
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
                if(!empty($fieldData['protected'])) {
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

                if(!empty($fieldData['protected'])) {
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

        if(!empty($fieldData['protected']) && !empty($result['components']['schemas'][$entityName]['properties'][$fieldName])) {
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
            'multilingual' => [
                'type' => 'boolean'
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
            $routePath = preg_replace('/:(\w+)/', '{$1}', $route['route']);
            $row = [
                'tags'        => [$route['params']['controller']],
                'summary'     => $route['summary'] ?? $route['description'],
                'description' => $route['description'],
                'operationId' => md5("{$routePath}_{$route['method']}"),
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

            $result['paths'][$routePath][$route['method']] = $row;
        }
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }
}
