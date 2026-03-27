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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entitySubscription',
    methods: ['DELETE'],
    summary: 'Unfollow stream',
    description: 'Unsubscribes the current user from the stream of the specified entity record.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['entityName', 'id'], 'properties' => ['entityName' => ['type' => 'string'], 'id' => ['type' => 'string']]]]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class UnfollowHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'entityName') || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        $entityName = (string) $data->entityName;
        $id         = (string) $data->id;

        if (empty($entityName) || empty($id)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        $this->getRecordService($entityName)->unfollow($id);

        return new BoolResponse(true);
    }
}
