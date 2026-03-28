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

namespace Atro\Handlers\Connection;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Connection/action/sendTestEmail',
    methods: [
        'POST',
    ],
    summary: 'Send a test email',
    description: 'Sends a test email via the specified connection. Accessible by administrators only.',
    tag: 'Connection',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'id',
                        'email',
                    ],
                    'properties' => [
                        'id'    => [
                            'type' => 'string',
                        ],
                        'email' => [
                            'type'   => 'string',
                            'format' => 'email',
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
class ConnectionSendTestEmailHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'id')) {
            throw new BadRequest('ID is required.');
        }

        if (!property_exists($data, 'email')) {
            throw new BadRequest('Email is required.');
        }

        $this->getRecordService('Connection')->sendTestEMail((string) $data->id, (string) $data->email);

        return new BoolResponse(true);
    }
}
