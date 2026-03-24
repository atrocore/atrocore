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

namespace Atro\Handlers\ClassificationAttribute;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ClassificationAttribute',
    methods: ['POST'],
    summary: 'Creates a ClassificationAttribute record',
    description: 'Creates one or more ClassificationAttribute records. If attributesIds is provided, creates one record per attribute.',
    tag: 'ClassificationAttribute',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object']]],
    ],
    responses: [
        200 => ['description' => 'Created ClassificationAttribute record', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
class ClassificationAttributeCreateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ClassificationAttribute', 'create')) {
            throw new Forbidden();
        }

        $data    = $this->getRequestBody($request);
        $service = $this->getRecordService('ClassificationAttribute');
        $entity  = null;

        if (property_exists($data, 'attributesIds')) {
            foreach ($data->attributesIds as $attributeId) {
                $input              = clone $data;
                $input->attributeId = $attributeId;
                unset($input->attributesIds);
                try {
                    $entity = $service->createEntity($input);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error($e->getMessage());
                }
            }
        } else {
            $entity = $service->createEntity($data);
        }

        if (!empty($entity)) {
            return new JsonResponse((array) $entity->getValueMap());
        }

        throw new Error();
    }
}
