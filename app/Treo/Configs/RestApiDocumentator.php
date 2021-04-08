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

namespace Treo\Configs;

return [
    'responseCode' => [
        'get'    => [200, 401, 403, 404, 500],
        'post'   => [201, 401, 403, 500],
        'put'    => [200, 401, 403, 404, 500],
        'delete' => [200, 401, 403, 404, 500],
    ],
    'common'       => [
        'ApiHeaders' => [
            ['key' => 'Accept', 'value' => 'application/json'],
            ['key' => 'Content-Type', 'value' => 'application/json'],
            ['key' => 'Basic-Authorization', 'value' => 'HASH'],
        ]
    ],
    'method'       => [
        'actionList'                       => [
            'ApiDescription' => [
                ['description' => 'Get list of %ss']
            ],
            'ApiMethod'      => [
                ['type' => 'GET']
            ],
            'ApiRoute'       => [
                ['name' => '/%s']
            ],
            'ApiParams'      => [
                [
                    'name'        => 'maxSize',
                    'type'        => 'integer',
                    'is_required' => '0',
                    'description' => 'Max size for paging'
                ],
                ['name' => 'offset', 'type' => 'integer', 'is_required' => '0', 'description' => 'Offset for paging'],
                ['name' => 'sortBy', 'type' => 'string', 'is_required' => '0', 'description' => 'Sort column'],
                ['name' => 'asc', 'type' => 'bool', 'is_required' => '0', 'description' => 'Sort order'],
            ],
            'ApiReturn'      => [
                [
                    'sample' => "{'total':'int','list': [{entityDeff}]}",
                ]
            ]
        ],
        'actionRead'                       => [
            'ApiDescription' => [
                ['description' => 'Get %s data']
            ],
            'ApiMethod'      => [
                ['type' => 'GET']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/:entityId']
            ],
            'ApiParams'      => [
                ['name' => 'entityId', 'type' => 'integer', 'is_required' => '1', 'description' => 'Entity id'],
            ],
            'ApiReturn'      => [
                [
                    'sample' => "{entityDeff}",
                ]
            ]
        ],
        'actionCreate'                     => [
            'ApiDescription' => [
                ['description' => 'Create %s']
            ],
            'ApiMethod'      => [
                ['type' => 'POST']
            ],
            'ApiRoute'       => [
                ['name' => '/%s']
            ],
            'ApiBody'        => [
                [
                    'sample' => "{entityDeff}"
                ]
            ],
            'ApiReturn'      => [
                [
                    'sample' => "{entityDeff}"
                ]
            ]
        ],
        'actionUpdate'                     => [
            'ApiDescription' => [
                ['description' => 'Edit %s']
            ],
            'ApiMethod'      => [
                ['type' => 'PUT']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/:entityId']
            ],
            'ApiParams'      => [
                ['name' => 'entityId', 'type' => 'integer', 'is_required' => '1', 'description' => 'Entity id'],
            ],
            'ApiBody'        => [
                [
                    'sample' => "{entityDeff}"
                ]
            ],
            'ApiReturn'      => [
                [
                    'sample' => "{entityDeff}"
                ]
            ]
        ],
        'actionDelete'                     => [
            'ApiDescription' => [
                ['description' => 'Delete %s']
            ],
            'ApiMethod'      => [
                ['type' => 'DELETE']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/:entityId']
            ],
            'ApiParams'      => [
                ['name' => 'entityId', 'type' => 'integer', 'is_required' => '1', 'description' => 'Entity id'],
            ],
            'ApiReturn'      => [
                [
                    'sample' => "true"
                ]
            ]
        ],
        'actionMassDelete'                 => [
            'ApiResponseCode' => [
                ['sample' => "[200,401,403,404,500]"]
            ],
            'ApiDescription'  => [
                ['description' => 'Mass delete of %s data']
            ],
            'ApiMethod'       => [
                ['type' => 'POST']
            ],
            'ApiRoute'        => [
                ['name' => '/%s/action/massDelete']
            ],
            'ApiBody'         => [
                [
                    'sample' => "{'ids': 'array'}"
                ]
            ],
            'ApiReturn'       => [
                [
                    'sample' => "{'count':'integer', 'ids':'array'}"
                ]
            ]
        ],
        'actionMassUpdate'                 => [
            'ApiResponseCode' => [
                ['sample' => "[200,401,403,404,500]"]
            ],
            'ApiDescription'  => [
                ['description' => 'Mass update of %s data']
            ],
            'ApiMethod'       => [
                ['type' => 'PUT']
            ],
            'ApiRoute'        => [
                ['name' => '/%s/action/massUpdate']
            ],
            'ApiParams'       => [
                [
                    'name'        => 'attributes',
                    'type'        => 'json',
                    'is_required' => '1',
                    'description' => 'Json of attributes and attributes value'
                ],
                ['name' => 'ids', 'type' => 'array', 'is_required' => '1', 'description' => 'Array of ids'],
            ],
            'ApiBody'         => [
                [
                    'sample' => "{'attributes': 'json', 'ids': 'array'}"
                ]
            ],
            'ApiReturn'       => [
                [
                    'sample' => "{'count':'integer', 'ids':'array'}"
                ]
            ]
        ],
        'actionListLinked'                 => [
            'ApiDescription' => [
                ['description' => 'Get linked entities for %s']
            ],
            'ApiMethod'      => [
                ['type' => 'GET']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/:entityId/:link']
            ],
            'ApiParams'      => [
                [
                    'name'        => 'entityId',
                    'type'        => 'string',
                    'is_required' => '1',
                    'description' => 'Entity id'
                ],
                [
                    'name'        => 'link',
                    'type'        => 'string',
                    'is_required' => '1',
                    'description' => 'Link name'
                ],
            ],
            'ApiReturn'      => [
                [
                    'sample' => "{'total':'integer', 'list': 'array'}"
                ]
            ]
        ],
        'actionCreateLink'                 => [
            'ApiDescription' => [
                ['description' => 'Create link for %s']
            ],
            'ApiMethod'      => [
                ['type' => 'POST']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/:entityId/:link']
            ],
            'ApiParams'      => [
                [
                    'name'        => 'entityId',
                    'type'        => 'string',
                    'is_required' => '1',
                    'description' => 'Entity id'
                ],
                [
                    'name'        => 'link',
                    'type'        => 'string',
                    'is_required' => '1',
                    'description' => 'Link name'
                ]
            ],
            'ApiBody'        => [
                [
                    'sample' => "{'ids': 'array'}"
                ]
            ],
            'ApiReturn'      => [
                [
                    'sample' => "'bool'"
                ]
            ]
        ],
        'actionRemoveLink'                 => [
            'ApiDescription' => [
                ['description' => 'Remove link from %s']
            ],
            'ApiMethod'      => [
                ['type' => 'DELETE']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/:entityId/:link']
            ],
            'ApiParams'      => [
                [
                    'name'        => 'entityId',
                    'type'        => 'string',
                    'is_required' => '1',
                    'description' => 'Entity id'
                ],
                [
                    'name'        => 'link',
                    'type'        => 'string',
                    'is_required' => '1',
                    'description' => 'Link name'
                ]
            ],
            'ApiReturn'      => [
                [
                    'sample' => "'bool'"
                ]
            ]
        ],
        'actionFollow'                     => [
            'ApiDescription' => [
                ['description' => 'Follow the %s stream']
            ],
            'ApiMethod'      => [
                ['type' => 'PUT']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/:entityId/subscription']
            ],
            'ApiParams'      => [
                [
                    'name'        => 'entityId',
                    'type'        => 'string',
                    'is_required' => '1',
                    'description' => 'Entity id'
                ]
            ],
            'ApiReturn'      => [
                [
                    'sample' => "'bool'"
                ]
            ]
        ],
        'actionUnfollow'                   => [
            'ApiDescription' => [
                ['description' => 'Unfollow the %s stream']
            ],
            'ApiMethod'      => [
                ['type' => 'DELETE']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/:entityId/subscription']
            ],
            'ApiParams'      => [
                [
                    'name'        => 'entityId',
                    'type'        => 'string',
                    'is_required' => '1',
                    'description' => 'Entity id'
                ]
            ],
            'ApiReturn'      => [
                [
                    'sample' => "'bool'"
                ]
            ]
        ],
        'actionMerge'                      => [
            'ApiDescription' => [
                ['description' => 'Merge %ss']
            ],
            'ApiMethod'      => [
                ['type' => 'POST']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/action/merge']
            ],
            'ApiBody'        => [
                [
                    'sample' => "{'attributes': 'json', 'targetId': 'string', 'sourceIds': 'array'}"
                ]
            ],
            'ApiReturn'      => [
                [
                    'sample' => "'bool'"
                ]
            ]
        ],
        'postActionGetDuplicateAttributes' => [
            'ApiDescription' => [
                ['description' => 'Get duplicate attributes from %s']
            ],
            'ApiMethod'      => [
                ['type' => 'POST']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/action/getDuplicateAttributes']
            ],
            'ApiBody'        => [
                [
                    'sample' => "{'id': 'string'}"
                ]
            ],
            'ApiReturn'      => [
                [
                    'sample' => "{entityDeff}"
                ]
            ]
        ],
        'postActionMassFollow'             => [
            'ApiDescription' => [
                ['description' => 'Mass follow  to %s entities']
            ],
            'ApiMethod'      => [
                ['type' => 'POST']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/action/massFollow']
            ],
            'ApiBody'        => [
                [
                    'sample' => "{'ids': 'array'}"
                ]
            ],
            'ApiReturn'      => [
                [
                    'sample' => "{'ids':'array', 'count':'integer'}"
                ]
            ]
        ],
        'postActionMassUnfollow'           => [
            'ApiDescription' => [
                ['description' => 'Mass unfollow  from %s entities']
            ],
            'ApiMethod'      => [
                ['type' => 'POST']
            ],
            'ApiRoute'       => [
                ['name' => '/%s/action/massFollow']
            ],
            'ApiBody'        => [
                [
                    'sample' => "{'ids': 'array'}"
                ]
            ],
            'ApiReturn'      => [
                [
                    'sample' => "{'ids':'array', 'count':'integer'}"
                ]
            ]
        ]
    ]
];
