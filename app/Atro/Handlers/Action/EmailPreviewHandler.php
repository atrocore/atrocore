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

use Atro\ActionTypes\SendEmail;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Action/{id}/emailPreview',
    methods: [
        'POST',
    ],
    summary: 'Preview email action output',
    description: 'Renders the email template configured in the Email action for the given entity record and returns the rendered subject, body, and recipients without sending.',
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
    ],
    requestBody: [
        'required' => false,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'entityId' => [
                            'type'        => 'string',
                            'description' => 'ID of the entity record that provides context for condition evaluation and template rendering. Its fields are accessible as `{{ entity.* }}` in Twig expressions. Does not select which records the action operates on — use `where` for that.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Rendered email data.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'subject'     => ['type' => 'string'],
                            'body'        => ['type' => 'string'],
                            'emailTo'     => ['type' => 'array', 'items' => ['type' => 'string']],
                            'emailCc'     => ['type' => 'array', 'items' => ['type' => 'string']],
                            'emailBcc'    => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ],
        404 => [
            'description' => 'Action record not found.',
        ],
    ],
)]
class EmailPreviewHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $actionId = $request->getAttribute('id');
        $data = $this->getRequestBody($request);

        $action = $this->getEntityManager()->getRepository('Action')->get($actionId);
        if (empty($action)) {
            throw new NotFound();
        }

        /** @var SendEmail $actionType */
        $actionType = $this->container->get(SendEmail::class);

        return new JsonResponse(
            $actionType->executeEmailPreview(
                $action,
                (string)($data->entityId ?? '')
            )
        );
    }
}
