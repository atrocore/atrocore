<?php
/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Handlers\Action;

use Atro\ActionTypes\AbstractAction;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Action/{id}/custom{customAction}Async',
    methods: [
        'POST',
    ],
    summary: 'Execute custom action asynchronously',
    description: 'Schedules the specified custom action (from the CustomActions namespace) as a background job '
        . 'and returns immediately with the job ID. The class must exist in data/custom-code/CustomActions/ '
        . 'and extend AbstractAction.',
    tag: 'Action',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Action record ID.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'customAction',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Custom action class name (e.g. "Test2" maps to CustomActions\\Test2).',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'The action has been scheduled as a background job.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'jobId' => [
                                'type'        => 'string',
                                'description' => 'ID of the created background job.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        404 => [
            'description' => 'Action record not found, or custom action class does not exist.',
        ],
    ],
)]
class CustomActionAsyncHandler extends AbstractActionTypeAsyncHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $customAction = (string)$request->getAttribute('customAction');
        $className = 'CustomActions\\' . $customAction;

        if (!class_exists($className) || !is_a($className, AbstractAction::class, true)) {
            throw new NotFound("Custom action class '$className' not found.");
        }

        return parent::process($request, $handler);
    }
}
