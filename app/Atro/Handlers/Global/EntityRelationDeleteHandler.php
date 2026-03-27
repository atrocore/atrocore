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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityRelation',
    methods: [
        'DELETE',
    ],
    summary: 'Remove relation between records',
    description: 'Unlinks one or more foreign records from the specified entity record.

**How to use:**
- `entityName` — the entity type (e.g. `Product`).
- `id` — the ID of the parent record to unlink from.
- `link` — the relation name as defined in `entityDefs.{entityName}.links` (e.g. `channels`).
- Pass `id` or `ids` (comma-separated) in query to unlink specific records. Alternatively, pass `id` or `ids` array in the request body.
- Pass `all=true` in query to unlink all related records at once.',
    tag: 'Global',
    parameters: [
        [
            'name'     => 'entityName',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type'    => 'string',
                'example' => 'Product',
            ],
        ],
        [
            'name'     => 'id',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'link',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type'    => 'string',
                'example' => 'channels',
            ],
        ],
        [
            'name'     => 'ids',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
            'description' => 'Comma-separated list of foreign record IDs to unlink',
        ],
        [
            'name'     => 'all',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'boolean',
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
class EntityRelationDeleteHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp         = $request->getQueryParams();
        $entityName = (string) ($qp['entityName'] ?? '');
        $id         = (string) ($qp['id'] ?? '');
        $link       = (string) ($qp['link'] ?? '');

        if ($entityName === '' || $id === '' || $link === '') {
            throw new BadRequest();
        }

        $service = $this->getRecordService($entityName);

        if (!empty($qp['all'])) {
            $service->unlinkAll($id, $link);
            return new BoolResponse(true);
        }

        $data          = $this->getRequestBody($request);
        $foreignIdList = [];

        if (isset($data->id)) {
            $foreignIdList[] = $data->id;
        }
        if (isset($data->ids) && is_array($data->ids)) {
            foreach ($data->ids as $foreignId) {
                $foreignIdList[] = $foreignId;
            }
        }

        if (!empty($qp['ids'])) {
            $foreignIdList = explode(',', $qp['ids']);
        }

        $result = false;
        foreach ($foreignIdList as $foreignId) {
            if ($service->unlinkEntity($id, $link, $foreignId)) {
                $result = true;
            }
        }

        if ($result) {
            return new BoolResponse(true);
        }

        throw new Error();
    }
}
