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

namespace Atro\Handlers\Action;

use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Http\Response\JsonResponse;

abstract class AbstractActionTypeAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id    = (string)$request->getAttribute('id');
        $input = $this->getRequestBody($request);

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'    => 'Execute Action',
            'type'    => 'ExecuteAction',
            'payload' => [
                'actionId' => $id,
                'input'    => json_decode(json_encode($input), true),
            ],
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);

        return new JsonResponse(['jobId' => $jobEntity->get('id')]);
    }
}
