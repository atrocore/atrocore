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

namespace Atro\Core\EntityTypeHandlers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/{id}/{link}',
    methods: ['DELETE'],
    summary: 'Unlinks entities',
    description: 'Removes a relation between the entity record and one or more foreign records.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'id',         'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'link',       'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'ids', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'], excludeEntities: ['UserProfile', 'MatchedRecord', 'Notification'])]
class RemoveLinkHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = (string) $request->getAttribute('id');
        $link       = (string) $request->getAttribute('link');

        if (empty($id) || empty($link)) {
            throw new BadRequest();
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

        $qp = $request->getQueryParams();
        if (!empty($qp['ids'])) {
            $foreignIdList = explode(',', $qp['ids']);
        }

        $service = $this->getRecordService($entityName);
        $result  = false;

        foreach ($foreignIdList as $foreignId) {
            if ($service->unlinkEntity($id, $link, $foreignId)) {
                $result = true;
            }
        }

        if ($result) {
            return new JsonResponse(['true' => true]);
        }

        throw new Error();
    }
}