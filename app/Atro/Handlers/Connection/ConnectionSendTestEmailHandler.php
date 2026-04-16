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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Connection/{id}/sendTestEmail',
    methods: [
        'POST',
    ],
    summary: 'Send a test email via a connection',
    description: 'Sends a test email to the specified address using the SMTP settings of the given connection record. Returns true on success, or throws an error with details on failure.',
    tag: 'Connection',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'The ID of the connection record to test.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['email'],
                    'properties' => [
                        'email' => [
                            'type'        => 'string',
                            'format'      => 'email',
                            'description' => 'The recipient email address to send the test message to.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if the test email was sent successfully',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Bad request — email address is missing or invalid',
        ],
        404 => [
            'description' => 'Connection record not found',
        ],
    ],
)]
class ConnectionSendTestEmailHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);
        $id   = $request->getAttribute('id');

        if (empty($data->email)) {
            throw new BadRequest('Email address is required.');
        }

        $this->getRecordService('Connection')->sendTestEMail($id, $data->email);

        return new BoolResponse(true);
    }
}
