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

namespace Atro\Handlers\App;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Espo\Core\ServiceFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/App/action/prepareScriptAttributes',
    methods: ['POST'],
    summary: 'Prepare script attributes',
    description: 'Evaluates script attributes for the specified entity.',
    tag: 'App',
    responses: [
        200 => ['description' => 'Prepared attribute values', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
        400 => ['description' => 'entityName and attributesIds are required'],
    ],
)]
class PrepareScriptAttributesHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly ServiceFactory $serviceFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = json_decode((string)$request->getBody()) ?? new \stdClass();

        if (empty($data->entityName) && (empty($data->attributesIds) || !is_array($data->attributesIds))) {
            throw new BadRequest();
        }

        return new JsonResponse(
            $this->serviceFactory->create('App')->prepareScriptAttributes($data->entityName, $data->attributesIds)
        );
    }
}
