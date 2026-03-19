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

namespace Atro\Handlers\App;

use Atro\Core\Container;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Espo\Core\ServiceFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/App/action/sendTestEmail',
    methods: ['POST'],
    summary: 'Send a test email',
    description: 'Sends a test email using the current outbound email settings. Admin only.',
    tag: 'App',
    responses: [
        200 => ['description' => 'true if sent successfully', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
        403 => ['description' => 'Forbidden'],
    ],
)]
class SendTestEmailHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly ServiceFactory $serviceFactory,
        private readonly Container      $container
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->container->get('user')->isAdmin()) {
            throw new Forbidden();
        }

        $data = json_decode((string)$request->getBody(), true) ?? [];

        return new BoolResponse(
            $this->serviceFactory->create('App')->sendTestEmail($data)
        );
    }
}
