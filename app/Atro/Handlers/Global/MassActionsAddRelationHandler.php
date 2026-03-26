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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{scope}/{link}/relation',
    methods: ['POST'],
    summary: 'Add mass relations',
    description: 'Creates relations between multiple records in bulk, using either IDs or a where clause.',
    tag: 'Global',
    parameters: [
        ['name' => 'scope', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
        ['name' => 'link',  'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object']]],
    ],
    responses: [
        200 => ['description' => 'Result', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
class MassActionsAddRelationHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $scope = (string) $request->getAttribute('scope');
        $link  = (string) $request->getAttribute('link');

        if (empty($scope) || empty($link)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($scope, 'edit')) {
            throw new Forbidden();
        }

        $data         = $this->getRequestBody($request);
        $relationData = json_decode(json_encode($data->data ?? new \stdClass()), true);
        $service      = $this->getServiceFactory()->create('MassActions');

        if (property_exists($data, 'where') && property_exists($data, 'foreignWhere')) {
            $where        = json_decode(json_encode($data->where), true);
            $foreignWhere = json_decode(json_encode($data->foreignWhere), true);

            return new JsonResponse($service->addRelationByWhere($where, $foreignWhere, $scope, $link, $relationData));
        }

        if (property_exists($data, 'ids') && property_exists($data, 'foreignIds')) {
            if (!is_array($data->ids) || !is_array($data->foreignIds)) {
                throw new BadRequest();
            }

            return new JsonResponse($service->addRelation($data->ids, $data->foreignIds, $scope, $link, $relationData));
        }

        throw new BadRequest();
    }
}
