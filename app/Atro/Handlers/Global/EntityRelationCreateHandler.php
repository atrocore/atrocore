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

namespace Atro\Handlers\Global;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityRelation',
    methods: [
        'POST',
    ],
    summary: 'Create relation between records',
    description: 'Links one or more foreign records to the specified entity record via the given relation.

**How to use:**
- `entityName` — the entity type (e.g. `Product`).
- `id` — the ID of the parent record to link from.
- `link` — the relation name as defined in `entityDefs.{entityName}.links` (e.g. `channels`).
- `ids` — array of foreign record IDs to link. Use `id` (singular) to link a single record.
- `massRelate` — set to `true` together with `where` to link all records matching a filter instead of specific IDs.
- `shouldDuplicateForeign` — set to `true` to duplicate the foreign record before linking.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'id',
                        'link',
                    ],
                    'properties' => [
                        'entityName'             => [
                            'type'    => 'string',
                            'example' => 'Product',
                        ],
                        'id'               => [
                            'type' => 'string',
                        ],
                        'link'                   => [
                            'type'    => 'string',
                            'example' => 'channels',
                        ],
                        'ids'                    => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        'massRelate'             => [
                            'type' => 'boolean',
                        ],
                        'where'                  => [
                            'type' => 'array',
                        ],
                        'selectData'             => [
                            'type' => 'object',
                        ],
                        'shouldDuplicateForeign' => [
                            'type' => 'boolean',
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
    ],
)]
class EntityRelationCreateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data       = $this->getRequestBody($request);
        $entityName = (string) ($data->entityName ?? '');
        $id         = (string) ($data->id ?? '');
        $link       = (string) ($data->link ?? '');

        if ($entityName === '' || $id === '' || $link === '') {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $shouldDuplicateForeign = !empty($data->shouldDuplicateForeign);
        $service                = $this->getRecordService($entityName);

        if (!empty($data->massRelate)) {
            if (!is_array($data->where)) {
                throw new BadRequest();
            }

            $where      = json_decode(json_encode($data->where), true);
            $selectData = isset($data->selectData) && is_array($data->selectData)
                ? json_decode(json_encode($data->selectData), true)
                : null;

            $result = $shouldDuplicateForeign
                ? $service->duplicateAndLinkEntityMass($id, $link, $where, $selectData)
                : $service->linkEntityMass($id, $link, $where, $selectData);

            $service->handleLinkEntitiesErrors($id, $link, $shouldDuplicateForeign);

            return new BoolResponse($result);
        }

        $foreignIdList = [];
        if (isset($data->ids) && is_array($data->ids)) {
            foreach ($data->ids as $foreignId) {
                $foreignIdList[] = $foreignId;
            }
        }

        $result = false;
        foreach ($foreignIdList as $foreignId) {
            $result = $shouldDuplicateForeign
                ? $service->duplicateAndLinkEntity($id, $link, $foreignId)
                : $service->linkEntity($id, $link, $foreignId);
        }

        if ($result) {
            $service->handleLinkEntitiesErrors($id, $link, $shouldDuplicateForeign);
            return new BoolResponse(true);
        }

        throw new Error();
    }
}
