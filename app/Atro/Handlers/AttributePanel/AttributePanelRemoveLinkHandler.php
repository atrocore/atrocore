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
    methods: ['DELETE'],
    summary: 'Unlinks entities for AttributePanel',
    description: 'Removes a relation between the AttributePanel record and one or more foreign records.',
    tag: 'AttributePanel',
    parameters: [
        ['name' => 'id',   'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
        ['name' => 'link', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
        ['name' => 'ids',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class AttributePanelRemoveLinkHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id   = (string) $request->getAttribute('id');
        $link = (string) $request->getAttribute('link');

        if (empty($id) || empty($link)) {
            throw new BadRequest();
        }

        $data          = $this->getRequestBody($request);
        $foreignIdList = [];

        if (isset($data->id)) {
            $foreignIdList[] = $data->id;
        }
        if (isset($data->ids) && is_array($data->ids)) {
            foreach ($data->ids as $foreignId) {
                $foreignIdList[] = $foreignId;
            }
        }

        $qp = $request->getQueryParams();
        if (!empty($qp['ids'])) {
            $foreignIdList = explode(',', $qp['ids']);
        }

        $service = $this->getRecordService('AttributePanel');
        $result  = false;

        foreach ($foreignIdList as $foreignId) {
            if ($service->unlinkEntity($id, $link, $foreignId)) {
                $result = true;
            }
        }

        if ($result) {
            return new BoolResponse(true);
        }

        throw new Error();
    }
}
