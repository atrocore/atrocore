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

namespace Atro\Handlers\AttributePanel;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/AttributePanel/action/unlinkAll',
    methods: ['POST'],
    summary: 'Unlinks all related records for AttributePanel',
    description: 'Removes all relations for a given link on the specified AttributePanel record.',
    tag: 'AttributePanel',
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class AttributePanelUnlinkAllHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'id') || !property_exists($data, 'link')) {
            throw new BadRequest();
        }

        $result = $this->getRecordService('AttributePanel')->unlinkAll((string) $data->id, (string) $data->link);

        return new JsonResponse(['true' => $result]);
    }
}
