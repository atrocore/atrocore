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

namespace Atro\Handlers\Installer;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Installer/action/setDbSettings',
    methods: ['POST'],
    summary: 'Set database settings',
    description: 'Saves database connection settings for installation. Only accessible before installation.',
    tag: 'Installer',
    auth: false,
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['host', 'dbname', 'user'], 'properties' => ['host' => ['type' => 'string'], 'dbname' => ['type' => 'string'], 'user' => ['type' => 'string'], 'password' => ['type' => 'string']]]]],
    ],
    responses: [
        200 => ['description' => 'Result', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
class InstallerSetDbSettingsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var \Atro\Services\Installer $installer */
        $installer = $this->getServiceFactory()->create('Installer');

        if ($installer->isInstalled()) {
            throw new Forbidden();
        }

        $post = (array) $this->getRequestBody($request);

        if (!isset($post['host']) || !isset($post['dbname']) || !isset($post['user'])) {
            throw new BadRequest();
        }

        return new JsonResponse($installer->setDbSettings($post));
    }
}
