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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Core\Utils\DataUtil;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Metadata',
    methods: ['GET'],
    summary: 'Returns all metadata for the current user',
    description: 'Returns all metadata for the current user.',
    tag: 'Global',
    responses: [
        200 => ['description' => 'Metadata object', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
class MetadataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new JsonResponse(DataUtil::toArray($this->getMetadata()->getAllForFrontend()));
    }
}
