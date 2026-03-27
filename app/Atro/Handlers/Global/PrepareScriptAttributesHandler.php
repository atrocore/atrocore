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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/App/action/prepareScriptAttributes',
    methods: [
        'POST',
    ],
    summary: 'Prepare script attributes',
    description: 'Evaluates script attributes for the specified entity.',
    tag: 'Global',
    responses: [
        200 => [
            'description' => 'Prepared attribute values',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName and attributesIds are required',
        ],
    ],
)]
class PrepareScriptAttributesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityName) && (empty($data->attributesIds) || !is_array($data->attributesIds))) {
            throw new BadRequest();
        }

        return new JsonResponse(
            $this->getServiceFactory()->create('App')->prepareScriptAttributes($data->entityName, $data->attributesIds)
        );
    }
}
