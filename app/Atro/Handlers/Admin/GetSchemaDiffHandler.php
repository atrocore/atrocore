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

namespace Atro\Handlers\Admin;

use Atro\Core\Exceptions\Forbidden;
use Psr\Container\ContainerInterface;
use Atro\Core\Http\Response\TextResponse;
use Atro\Core\Routing\Route;
use Atro\Core\Utils\Database\Schema\Schema;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Admin/getSchemaDiff',
    methods: ['GET'],
    summary: 'Get database schema diff',
    description: 'Returns SQL queries needed to synchronize the database schema with the current metadata. Admin only.',
    tag: 'Admin',
    responses: [
        200 => ['description' => 'SQL diff queries as plain text', 'content' => ['text/plain' => ['schema' => ['type' => 'string', 'example' => 'ALTER TABLE product ADD COLUMN sku VARCHAR(255);']]]],
        403 => ['description' => 'Forbidden'],
    ],
)]
class GetSchemaDiffHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->container->get('user')->isAdmin()) {
            throw new Forbidden();
        }

        /** @var Schema $schema */
        $schema = $this->container->get('schema');

        $result = '';
        foreach ($schema->getDiffQueries() as $query) {
            $result .= $query . PHP_EOL;
        }

        return new TextResponse($result);
    }
}
