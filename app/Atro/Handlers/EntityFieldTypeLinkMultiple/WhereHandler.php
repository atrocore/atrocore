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

namespace Atro\Handlers\EntityFieldTypeLinkMultiple;

use Atro\Core\Routing\Route;
use Atro\Handlers\EntityFieldTypeLink\WhereHandler as LinkWhereHandler;

#[Route(
    path: '/EntityFieldTypeLinkMultiple/{id}/where',
    methods: [
        'POST',
    ],
    summary: 'Get the where-filter of a multiple link field',
    description: 'Returns the `where` filter configured for a multiple link field, with the Twig expressions resolved. The saved record
(`entityId`) is available as `entity`, and the current values of the form that are not saved (`uiRecord`) as `uiRecord`,
like in the expressions of the actions: `{{ entity.name }}`, `{{ uiRecord.name }}`. The expressions are resolved for a
record that is not saved yet too; its `entity` is a blank record.

Use this endpoint before opening a link-field picker or autocomplete to obtain a concrete,
fully-resolved filter that can be forwarded as the `where` parameter to collection requests.

Every string of the filter is rendered by itself and is not escaped. An expression that is the whole value and
renders to a JSON list, e.g. `{{ ["a", "b"]|json_encode|raw }}`, gives a list of values. A string that cannot be
rendered is returned as it is.

**Preconditions:**
- The caller must have read access to the entity of the field and the field must not be
  forbidden by field-level ACL.
- When `entityId` is supplied the caller must also have read access to that record;
  otherwise 403 is returned.
- The values of `uiRecord` of the fields that the caller may not read are ignored.

**When neither `entityId` nor `uiRecord` is supplied** the `where` array is returned as-is, without Twig rendering.',
    tag: 'EntityFieldTypeLinkMultiple',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'schema'      => [
                'type' => 'string',
            ],
            'description' => 'ID of the field, e.g. "Product_brand".',
        ],
    ],
    requestBody: [
        'required' => false,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'entityId' => [
                            'type'        => 'string',
                            'description' => 'ID of the saved record that the form edits. Its fields are accessible as `{{ entity.* }}` in the Twig expressions. Omitted for a record that is not saved yet.',
                        ],
                        'uiRecord' => [
                            'type'                 => 'object',
                            'description'          => 'Current unsaved form values, keyed by field name, e.g. {"measurementOccasion": "serviceRepair"}. Accessible as `{{ uiRecord.* }}` in the Twig expressions; the values of the saved record are not changed.',
                            'additionalProperties' => true,
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Rendered where-filter. Returns an empty array under `where` when no filter is configured for the field.',
            'content'     => [
                'application/json' => [
                    'schema'  => [
                        'type'       => 'object',
                        'required'   => [
                            'where',
                        ],
                        'properties' => [
                            'where' => [
                                'type'        => 'array',
                                'description' => 'The groups of rules of the filter, with the expressions resolved.',
                                'items'       => [
                                    'type' => 'object',
                                ],
                            ],
                        ],
                    ],
                    'example' => [
                        'where' => [
                            [
                                'condition' => 'AND',
                                'rules'     => [
                                    [
                                        'id'       => 'function',
                                        'field'    => 'function',
                                        'type'     => 'string',
                                        'operator' => 'array_any_of',
                                        'value'    => [
                                            'remanufacturer',
                                        ],
                                    ],
                                ],
                                'valid'     => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Access denied: the caller lacks read permission for the entity of the field, the field is forbidden by field-level ACL, or the caller cannot read the specified record.',
        ],
        404 => [
            'description' => 'Not found — no multiple link field with the given ID or no record with the given ID exists.',
        ],
    ],
)]
class WhereHandler extends LinkWhereHandler
{
    protected function getFieldEntityName(): string
    {
        return 'EntityFieldTypeLinkMultiple';
    }
}
