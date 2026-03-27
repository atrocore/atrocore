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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Composer/updateModule',
    methods: ['PATCH'],
    summary: 'Update module settings',
    description: 'Update module settings (e.g. set a specific version). Changes are queued until runUpdate is called. Accessible by administrators only.',
    tag: 'Composer',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['id'], 'properties' => ['id' => ['type' => 'string', 'example' => 'atrocore/pim'], 'version' => ['type' => 'string', 'example' => '1.2.3']]]]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class ComposerUpdateModuleHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $data = json_decode(json_encode($this->getRequestBody($request)), true);

        if (!empty($data['id'])) {
            $version = (!empty($data['version'])) ? $data['version'] : null;
            $this->getServiceFactory()->create('Composer')->updateModule($data['id'], $version);

            return new BoolResponse(true);
        }

        throw new NotFound();
    }
}
