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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Core\Twig\Twig;
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
            'name'     => 'id',
            'in'       => 'path',
            'required' => true,
            'schema'   => ['type' => 'string'],
            'description' => 'EntityField record ID.',
        ],
        [
            'name'     => 'recordId',
            'in'       => 'query',
            'required' => false,
            'schema'   => ['type' => 'string'],
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
                                'valid' => true,
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
    /** Field types that support a `where` filter (link to a foreign entity). */
    private const FIELD_TYPES_WITH_WHERE = ['link', 'linkMultiple'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityFieldId = (string) $request->getAttribute('id');
        $recordId      = (string) ($request->getQueryParams()['recordId'] ?? '');

        // 1. Load EntityField record.
        $entityField = $this->getEntityManager()->getRepository('EntityField')->get($entityFieldId);
        if (empty($entityField)) {
            throw new NotFound();
        }

        // 2. Validate that this field type can carry a where-filter.
        $fieldType = (string) $entityField->get('type');
        if (!in_array($fieldType, self::FIELD_TYPES_WITH_WHERE, true)) {
            throw new BadRequest(
                sprintf(
                    "Field type '%s' does not support a where-filter. Only %s fields are supported.",
                    $fieldType,
                    implode(', ', self::FIELD_TYPES_WITH_WHERE)
                )
            );
        }

        $entityName = (string) $entityField->get('entityId');
        $fieldCode  = (string) $entityField->get('code');

        // 3. ACL: caller must have read access to the parent entity scope.
        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        // 4. ACL: field must not be forbidden at field level.
        $forbiddenFields = $this->getAcl()->getScopeForbiddenFieldList($entityName, 'read');
        if (in_array($fieldCode, $forbiddenFields, true)) {
            throw new Forbidden();
        }

        // 5. Read the where-filter from compiled entity metadata.
        $where = $this->getMetadata()->get(['entityDefs', $entityName, 'fields', $fieldCode, 'where']) ?? [];

        if (empty($where)) {
            return new JsonResponse(['where' => []]);
        }

        // 6. If no recordId is given, return the raw where without Twig rendering.
        if ($recordId === '') {
            return new JsonResponse(['where' => $where]);
        }

        // 7. Load the context record with ACL check.
        $record = $this->getEntityManager()->getRepository($entityName)->get($recordId);
        if (empty($record)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->checkEntity($record, 'read')) {
            throw new Forbidden();
        }

        // 8. Render Twig expressions inside the where-filter JSON.
        $whereJson     = json_encode($where);
        $renderedJson  = $this->getTwig()->renderTemplate($whereJson, ['entity' => $record]);
        $renderedWhere = @json_decode($renderedJson, true);

        if (!is_array($renderedWhere)) {
            // Rendering produced invalid JSON — return the original unrendered filter.
            return new JsonResponse(['where' => $where]);
        }

        return new JsonResponse(['where' => $renderedWhere]);
    }

    private function getTwig(): Twig
    {
        return $this->container->get('twig');
    }
}
