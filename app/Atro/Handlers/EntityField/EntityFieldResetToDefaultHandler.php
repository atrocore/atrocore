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

namespace Atro\Handlers\EntityField;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/EntityField/action/resetToDefault',
    methods: [
        'POST',
    ],
    summary: 'Reset entity field to default',
    description: 'Resets an entity field configuration to its default values. Accessible by administrators only.',
    tag: 'EntityField',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'scope',
                        'field',
                    ],
                    'properties' => [
                        'scope' => [
                            'type' => 'string',
                        ],
                        'field' => [
                            'type' => 'string',
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
class EntityFieldResetToDefaultHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'scope') || !property_exists($data, 'field')) {
            throw new BadRequest();
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $this->getRecordService('EntityField')->resetToDefault($data->scope, $data->field);

        return new BoolResponse(true);
    }
}
