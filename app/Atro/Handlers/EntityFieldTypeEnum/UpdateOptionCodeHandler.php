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

namespace Atro\Handlers\EntityFieldTypeEnum;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/EntityFieldTypeEnum/{id}/updateOptionCode',
    methods: [
        'POST',
    ],
    summary: 'Update the code of an option of a list',
    description: 'Changes the code of an option of a list field and replaces the value in the existing records and the translation of the option. Accessible by administrators only.',
    tag: 'EntityFieldTypeEnum',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the field, e.g. "Product_productStatus"',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'oldValue',
                        'newValue',
                    ],
                    'properties' => [
                        'oldValue' => [
                            'type'    => 'string',
                            'example' => 'draft',
                        ],
                        'newValue' => [
                            'type'    => 'string',
                            'example' => 'new',
                        ],
                    ],
                ],
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
        403 => [
            'description' => 'Forbidden — only administrators can change the codes of the options',
        ],
        404 => [
            'description' => 'Not found — no list field with the given ID exists',
        ],
    ],
)]
class UpdateOptionCodeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        $this->getRecordService($this->getFieldEntityName())
            ->updateOptionCode((string)$request->getAttribute('id'), (string)$data->oldValue, (string)$data->newValue);

        return new BoolResponse(true);
    }

    /**
     * The subtype of the entity field the options belong to.
     */
    protected function getFieldEntityName(): string
    {
        return 'EntityFieldTypeEnum';
    }
}
