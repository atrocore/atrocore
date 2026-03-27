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
    path: '/Attribute/action/addAttributeValue',
    methods: [
        'POST',
    ],
    summary: 'Add attribute value to entities',
    description: 'Adds an attribute value to one or more entity records.',
    tag: 'Attribute',
    responses: [
        200 => [
            'description' => 'true on success',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Invalid input',
        ],
        403 => [
            'description' => 'Forbidden',
        ],
    ],
)]
class AddAttributeValueHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityName) || empty($data->entityId)) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'ids') && !property_exists($data, 'where')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($data->entityName, 'edit')) {
            throw new Forbidden();
        }

        if (
            $this->getMetadata()->get(['scopes', $data->entityName, 'hasAttribute'])
            && $this->getMetadata()->get(['scopes', $data->entityName, 'disableAttributeLinking'])
        ) {
            throw new BadRequest();
        }

        $result = $this->getServiceFactory()->create('Attribute')->addAttributeValue(
            $data->entityName,
            $data->entityId,
            $data->where ?? null,
            $data->ids ?? null
        );

        return new BoolResponse($result);
    }
}
