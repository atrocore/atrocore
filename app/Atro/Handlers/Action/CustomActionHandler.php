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

namespace Atro\Handlers\Action;

use Atro\ActionTypes\AbstractAction;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Action/{id}/custom{customAction}',
    methods: [
        'POST',
    ],
    summary: 'Execute custom action',
    description: 'Executes a custom action class from the CustomActions namespace. '
    . 'The class must exist in data/custom-code/CustomActions/ and extend AbstractAction. '
    . 'Use the console command `php console.php create-action <ClassName>` to scaffold the class.',
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
            'description' => 'Execution result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'success' => [
                                'type' => 'boolean',
                            ],
                            'message' => [
                                'type'     => 'string',
                                'nullable' => true,
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
class CustomActionHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id           = (string)$request->getAttribute('id');
        $customAction = (string)$request->getAttribute('customAction');
        $input        = $this->getRequestBody($request);

        $className = 'CustomActions\\' . $customAction;

        if (!class_exists($className) || !is_a($className, AbstractAction::class, true)) {
            throw new NotFound("Custom action class '$className' not found.");
        }

        $action = $this->getEntityManager()->getRepository('Action')->get($id);
        if (empty($action)) {
            throw new NotFound();
        }

        /** @var AbstractAction $instance */
        $instance = $this->container->get($className);

        if (!method_exists($instance, 'executeNow')) {
            throw new BadRequest("Custom action class '$className' does not implement executeNow().");
        }

        $success = $instance->executeNow($action, $input);

        return new JsonResponse(['success' => $success, 'message' => $this->getLanguage()->translate('Done')]);
    }
}
