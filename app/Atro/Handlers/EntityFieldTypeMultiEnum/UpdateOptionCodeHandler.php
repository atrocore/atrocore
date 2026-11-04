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

namespace Atro\Handlers\EntityFieldTypeMultiEnum;

use Atro\Core\Routing\Route;
use Atro\Handlers\EntityFieldTypeEnum\UpdateOptionCodeHandler as EnumUpdateOptionCodeHandler;

#[Route(
    path: '/EntityFieldTypeMultiEnum/{id}/updateOptionCode',
    methods: [
        'POST',
    ],
    summary: 'Update the code of an option of a multi-value list',
    description: 'Changes the code of an option of a multi-value list field and replaces the value in the existing records and the translation of the option. Accessible by administrators only.',
    tag: 'EntityFieldTypeMultiEnum',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the field, e.g. "Product_productStatus"',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'oldValue',
                        'newValue',
                    ],
                    'properties' => [
                        'oldValue' => [
                            'type'    => 'string',
                            'example' => 'draft',
                        ],
                        'newValue' => [
                            'type'    => 'string',
                            'example' => 'new',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Success',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden — only administrators can change the codes of the options',
        ],
        404 => [
            'description' => 'Not found — no multi-value list field with the given ID exists',
        ],
    ],
)]
class UpdateOptionCodeHandler extends EnumUpdateOptionCodeHandler
{
    protected function getFieldEntityName(): string
    {
        return 'EntityFieldTypeMultiEnum';
    }
}
