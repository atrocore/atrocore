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

namespace Atro\Handlers\Unit;

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Unit/{id}/setDefault',
    methods: [
        'POST',
    ],
    summary: 'Sets a unit as default',
    description: 'Marks the specified unit as the default unit for its measure. Accessible by administrators only.',
    tag: 'Unit',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Unit record ID',
            'schema'      => [
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
        403 => [
            'description' => 'Forbidden',
        ],
        404 => [
            'description' => 'Unit not found',
        ],
    ],
)]
class UnitSetDefaultHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string)$request->getAttribute('id');

        $unit = $this->getEntityManager()->getEntity('Unit', $id);
        if (empty($unit)) {
            throw new NotFound();
        }

        $this->getRecordService('Unit')->setUnitAsDefault($unit);

        return new BoolResponse(true);
    }
}
