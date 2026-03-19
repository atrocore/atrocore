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

namespace Atro\Handlers\Attribute;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Attribute/action/removeAttributeValue',
    methods: ['POST'],
    summary: 'Remove attribute value from an entity',
    description: 'Removes an attribute value from an entity record.',
    tag: 'Attribute',
    responses: [
        200 => ['description' => 'true on success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
        400 => ['description' => 'Invalid input'],
        403 => ['description' => 'Forbidden'],
    ],
)]
class RemoveAttributeValueHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = json_decode((string)$request->getBody()) ?? new \stdClass();

        if (empty($data->entityName) || empty($data->attributeId) || empty($data->entityId)) {
            throw new BadRequest();
        }

        if (!$this->container->get('acl')->check($data->entityName, 'edit')) {
            throw new Forbidden();
        }

        if (
            $this->container->get('metadata')->get(['scopes', $data->entityName, 'hasAttribute'])
            && $this->container->get('metadata')->get(['scopes', $data->entityName, 'disableAttributeLinking'])
        ) {
            throw new BadRequest();
        }

        $result = $this->getServiceFactory()->create('Attribute')->removeAttributeValue(
            $data->entityName,
            $data->entityId,
            $data->attributeId
        );

        return new BoolResponse($result);
    }
}
