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
use Atro\Core\Exceptions\Error;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/AttributePanel/{id}/{link}',
    methods: [
        'POST',
    ],
    summary: 'Links entities for AttributePanel',
    description: 'Creates a relation between the AttributePanel record and one or more foreign records.',
    tag: 'AttributePanel',
    parameters: [
        [
            'name'     => 'id',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'link',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type' => 'string',
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
class AttributePanelCreateLinkHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id   = (string) $request->getAttribute('id');
        $link = (string) $request->getAttribute('link');

        if (empty($id) || empty($link)) {
            throw new BadRequest();
        }

        $data                  = $this->getRequestBody($request);
        $shouldDuplicateForeign = !empty($data->shouldDuplicateForeign);
        $service               = $this->getRecordService('AttributePanel');

        if (!empty($data->massRelate)) {
            if (!is_array($data->where)) {
                throw new BadRequest();
            }
            $where      = json_decode(json_encode($data->where), true);
            $selectData = isset($data->selectData) && is_array($data->selectData)
                ? json_decode(json_encode($data->selectData), true)
                : null;

            $result = $shouldDuplicateForeign
                ? $service->duplicateAndLinkEntityMass($id, $link, $where, $selectData)
                : $service->linkEntityMass($id, $link, $where, $selectData);

            $service->handleLinkEntitiesErrors($id, $link, $shouldDuplicateForeign);

            return new BoolResponse($result);
        }

        $foreignIdList = [];
        if (isset($data->id)) {
            $foreignIdList[] = $data->id;
        }
        if (isset($data->ids) && is_array($data->ids)) {
            foreach ($data->ids as $foreignId) {
                $foreignIdList[] = $foreignId;
            }
        }

        $result = false;
        foreach ($foreignIdList as $foreignId) {
            $result = $shouldDuplicateForeign
                ? $service->duplicateAndLinkEntity($id, $link, $foreignId)
                : $service->linkEntity($id, $link, $foreignId);
        }

        if ($result) {
            $service->handleLinkEntitiesErrors($id, $link, $shouldDuplicateForeign);
            return new BoolResponse(true);
        }

        throw new Error();
    }
}
