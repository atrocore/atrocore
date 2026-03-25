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

namespace Atro\Handlers\Classification;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Atro\Services\Record;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Classification/action/relateRecords',
    methods: ['POST'],
    summary: 'Relate records to classifications',
    description: 'Sets classification relations for a given entity record. Replaces existing classifications with the provided list.',
    tag: 'Classification',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['entityName', 'entityId', 'classificationsIds'], 'properties' => ['entityName' => ['type' => 'string'], 'entityId' => ['type' => 'string'], 'classificationsIds' => ['type' => 'array', 'items' => ['type' => 'string']]]]]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class ClassificationRelateRecordsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (
            !property_exists($data, 'entityName')
            || !property_exists($data, 'entityId')
            || !property_exists($data, 'classificationsIds')
        ) {
            throw new BadRequest();
        }

        /** @var Record $service */
        $service = $this->getServiceFactory()->create($data->entityName);

        if (empty($data->classificationsIds)) {
            $service->unlinkAll($data->entityId, 'classifications');
        } else {
            $recordClassificationsIds = [];

            $res = $service->findLinkedEntities($data->entityId, 'classifications', []);
            if (!empty($res['collection'][0])) {
                $recordClassificationsIds = array_column($res['collection']->toArray(), 'id');
            }

            foreach ($recordClassificationsIds as $recordClassificationId) {
                if (!in_array($recordClassificationId, $data->classificationsIds)) {
                    $service->unlinkEntity($data->entityId, 'classifications', $recordClassificationId);
                }
            }

            foreach ($data->classificationsIds as $classificationId) {
                if (!in_array($classificationId, $recordClassificationsIds)) {
                    $service->linkEntity($data->entityId, 'classifications', $classificationId);
                }
            }
        }

        return new BoolResponse(true);
    }
}
