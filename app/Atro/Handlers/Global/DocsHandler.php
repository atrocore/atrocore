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
use Atro\Core\Http\Response\TextResponse;
use Atro\Core\Routing\Route;
use Atro\Core\DocsManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/docs',
    methods: [
        'GET',
    ],
    summary: 'Get documentation page',
    description: 'Returns the markdown content of a documentation page for the specified module.',
    tag: 'Global',
    parameters: [
        [
            'name'        => 'module',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Module ID, "_sidebar", or "README"',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'page',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Page path within the module docs',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Markdown content',
            'content'     => [
                'text/plain' => [
                    'schema' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'module is required',
        ],
        404 => [
            'description' => 'Page not found',
        ],
    ],
    hidden: true,
    skipActionHistory: true,
)]
class DocsHandler implements MiddlewareInterface
{
    public function __construct(private readonly DocsManager $docs)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp     = $request->getQueryParams();
        $module = preg_replace('/\.md$/i', '', (string)($qp['module'] ?? ''));
        $page   = preg_replace('/\.md$/i', '', (string)($qp['page'] ?? ''));

        if ($module === '') {
            return new ErrorResponse(400, 'module is required');
        }

        $scheme      = $request->getUri()->getScheme();
        $host        = $request->getUri()->getHost();
        $assetBaseUrl = $scheme . '://' . $host . '/api/docsAsset?';

        $content = $this->docs->getMarkdown($module, $page, $assetBaseUrl);

        if ($content === null) {
            return new ErrorResponse(404, 'Page not found');
        }

        return new TextResponse($content);
    }
}
