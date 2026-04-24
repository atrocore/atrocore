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

namespace Atro\Handlers\EntityField;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/EntityField/{id}/prepareFieldWhere',
    methods: [
        'GET',
    ],
    summary: 'Prepare rendered where-filter for a link field',
    description: 'Returns the `where` filter array configured for a link/linkMultiple EntityField,
with Twig expressions resolved against the specified record.

Use this endpoint before opening a link-field picker or autocomplete to obtain a concrete,
fully-resolved filter that can be forwarded as the `where` parameter to collection requests.

**Preconditions:**
- The EntityField must be of type `link` or `linkMultiple`.
- The caller must have read access to the parent entity scope and the field must not be
  forbidden by field-level ACL.
- When `recordId` is supplied the caller must also have read access to that record;
  otherwise 403 is returned.

**When `recordId` is omitted** (e.g. creating a new record) the `where` array is returned
as-is, without Twig rendering. Twig variables will be absent from the result, which may
produce an incomplete filter — the frontend should decide whether to apply it or skip
filtering for new records.',
    tag: 'EntityField',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'schema'      => ['type' => 'string'],
            'description' => 'EntityField record ID.',
        ],
        [
            'name'        => 'recordId',
            'in'          => 'query',
            'required'    => false,
            'schema'      => ['type' => 'string'],
            'description' => 'ID of the record currently being edited. Used as `entity` in Twig rendering.',
        ],
    ],
    responses: [
        200 => [
            'description' => 'Rendered where-filter. Returns an empty array under `where` when no filter is configured for the field.',
            'content'     => [
                'application/json' => [
                    'schema'  => [
                        'type'       => 'object',
                        'required'   => ['where'],
                        'properties' => [
                            'where' => [
                                'type'  => 'array',
                                'items' => [
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
                                        'id'       => 'extensibleEnums',
                                        'field'    => 'extensibleEnums',
                                        'type'     => 'string',
                                        'operator' => 'linked_with',
                                        'value'    => ['u65264ae346eef30c'],
                                    ],
                                ],
                                'valid'     => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The specified EntityField does not support a where-filter (field type is not link or linkMultiple).',
        ],
        403 => [
            'description' => 'Access denied: the caller lacks read permission for the parent entity scope, the field is forbidden by field-level ACL, or the caller cannot read the specified record.',
        ],
        404 => [
            'description' => 'EntityField or the specified record not found.',
        ],
    ],
)]
class EntityFieldPrepareFieldWhereHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityFieldId = (string)$request->getAttribute('id');
        $recordId      = (string)($request->getQueryParams()['recordId'] ?? '');

        return new JsonResponse([
            'where' => $this->getRecordService('EntityField')->prepareFieldWhere($entityFieldId, $recordId)
        ]);
    }
}
