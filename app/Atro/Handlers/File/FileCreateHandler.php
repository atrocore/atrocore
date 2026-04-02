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

namespace Atro\Handlers\File;

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/File',
    methods: [
        'POST',
    ],
    summary: 'Creates a File record',
    description: 'Creates a new file record via API.',
    tag: 'File',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Created file record',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
    ],
)]
class FileCreateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('File', 'create')) {
            throw new Forbidden();
        }

        $data          = $this->getRequestBody($request);
        $data->fromApi = true;

        $service = $this->getRecordService('File');

        $result = $service->createEntity($data);

        if (!is_string($result)) {
            return new JsonResponse($result);
        }

        $entity = $service->prepareEntityById($result);
        if (empty($entity)) {
            throw new Error();
        }

        return new JsonResponse((array) $entity->getValueMap());
    }
}
