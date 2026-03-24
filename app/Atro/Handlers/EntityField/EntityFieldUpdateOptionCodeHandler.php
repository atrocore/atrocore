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

namespace Atro\Handlers\EntityField;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/EntityField/action/updateOptionCode',
    methods: ['POST'],
    summary: 'Update the code of an option from static list',
    description: 'Update the code of an option from static list and static multi list, replacing the value in the database. Accessible by administrators only.',
    tag: 'EntityField',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['scope', 'field', 'oldValue', 'newValue'], 'properties' => ['scope' => ['type' => 'string', 'example' => 'Product'], 'field' => ['type' => 'string', 'example' => 'productStatus'], 'oldValue' => ['type' => 'string', 'example' => 'draft'], 'newValue' => ['type' => 'string', 'example' => 'new']]]]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class EntityFieldUpdateOptionCodeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        if (
            !property_exists($data, 'scope')
            || !property_exists($data, 'field')
            || !property_exists($data, 'oldValue')
            || !property_exists($data, 'newValue')
        ) {
            throw new BadRequest();
        }

        return new JsonResponse(['true' => $this->getRecordService('EntityField')->updateOptionCode($data->scope, $data->field, $data->oldValue, $data->newValue)]);
    }
}
