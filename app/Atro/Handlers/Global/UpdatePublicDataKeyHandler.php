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

use Atro\Core\DataManager;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/publicData',
    methods: [
        'POST',
    ],
    summary: 'Write to public data store',
    description: 'Used internally by the AtroCore UI. External integrations should ignore this endpoint.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'key',
                        'value',
                    ],
                    'properties' => [
                        'key'   => [
                            'type'    => 'string',
                            'example' => 'myUserId123RefreshListScope',
                        ],
                        'value' => [
                            'nullable' => true,
                            'example'  => null,
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if written, false if key is missing or reserved',
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
class UpdatePublicDataKeyHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'key') || !property_exists($data, 'value') || in_array($data->key, ['dataTimestamp', 'notReadCount'])) {
            return new BoolResponse(false);
        }

        DataManager::pushPublicData($data->key, $data->value);

        return new BoolResponse(true);
    }
}
