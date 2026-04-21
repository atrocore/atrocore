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

use Atro\Core\Http\Response\ErrorResponse;
use Atro\Core\Routing\Route;
use Atro\Core\DocsManager;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/docsAsset',
    methods: [
        'GET',
    ],
    summary: 'Get documentation asset',
    description: 'Returns a binary asset file (image) from the documentation of the specified module.',
    tag: 'Global',
    parameters: [
        [
            'name'        => 'module',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Module ID or "README"',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'asset',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Relative asset path within the module docs directory',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Asset file content',
            'content'     => [
                'image/*' => [
                    'schema' => [
                        'type'   => 'string',
                        'format' => 'binary',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'module or asset is missing, or asset path is invalid',
        ],
        404 => [
            'description' => 'Asset not found',
        ],
    ],
    hidden: true,
    skipActionHistory: true,
)]
class DocsAssetHandler implements MiddlewareInterface
{
    public function __construct(private readonly DocsManager $docs)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp     = $request->getQueryParams();
        $module = (string)($qp['module'] ?? '');
        $asset  = (string)($qp['asset'] ?? '');

        if (str_contains($asset, '..')) {
            return new ErrorResponse(400, 'Invalid asset path');
        }

        $result = $this->docs->getAsset($module, $asset);

        if ($result === null) {
            return new ErrorResponse(404, 'Asset not found');
        }

        return new Response(
            200,
            [
                'Content-Type'  => $result['mime'],
                'Cache-Control' => 'public, max-age=86400',
            ],
            file_get_contents($result['path'])
        );
    }
}
