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

namespace Atro\Handlers\Composer;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Composer/deleteModule',
    methods: ['DELETE'],
    summary: 'Delete a module',
    description: 'Queues a module for deletion. Accessible by administrators only.',
    tag: 'Composer',
    parameters: [
        ['name' => 'id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class ComposerDeleteModuleHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $id = null;

        $data = json_decode(json_encode($this->getRequestBody($request)), true);
        if (!empty($data['id'])) {
            $id = $data['id'];
        }

        $qp = $request->getQueryParams();
        if (!empty($qp['id'])) {
            $id = $qp['id'];
        }

        if (!empty($id)) {
            return new JsonResponse(['true' => $this->getServiceFactory()->create('Composer')->deleteModule($id)]);
        }

        throw new NotFound();
    }
}
