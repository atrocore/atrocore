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

namespace Atro\Handlers\ExtensibleEnum;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExtensibleEnum/action/getExtensibleEnumOptions',
    methods: ['GET'],
    summary: 'Get extensible enum options',
    description: 'Returns the options for a single extensible enum.',
    tag: 'ExtensibleEnum',
    parameters: [
        ['name' => 'extensibleEnumId', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'List of options', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ExtensibleEnumOption']]]]],
    ],
    entities: ['ExtensibleEnumOption'],
)]
class ExtensibleEnumGetOptionsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        if (empty($qp['extensibleEnumId'])) {
            throw new BadRequest();
        }

        return new JsonResponse($this->getRecordService('ExtensibleEnum')->getExtensibleEnumOptions((string) $qp['extensibleEnumId']));
    }
}
