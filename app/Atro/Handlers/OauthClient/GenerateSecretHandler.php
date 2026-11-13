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

namespace Atro\Handlers\OauthClient;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/OauthClient/{id}/generateSecret',
    methods: [
        'POST',
    ],
    summary: 'Generate a new OAuth client secret',
    description: 'Generates a new secret of the OAuth client and returns it. Only the hash of the secret is stored, so it is returned only once; the previous secret stops working and the tokens issued to the client are revoked.',
    tag: 'OauthClient',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the OAuth client.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'The client credentials.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'clientId'     => [
                                'type' => 'string',
                            ],
                            'clientSecret' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden.',
        ],
        404 => [
            'description' => 'OAuth client not found.',
        ],
    ],
    skipActionHistory: true,
)]
class GenerateSecretHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string)$request->getAttribute('id');

        return new JsonResponse([
            'clientId'     => $id,
            'clientSecret' => $this->getRecordService('OauthClient')->generateSecret($id),
        ]);
    }
}
