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

namespace Atro\Handlers\Global;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/background',
    methods: [
        'GET',
    ],
    summary: 'Get background data',
    description: 'Get background data.',
    tag: 'Global',
    auth: false,
    responses: [
        200 => [
            'description' => 'Background data',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'imageName'  => [
                                'type'    => 'string',
                                'example' => 'pexels-pixabay-260689.jpg',
                            ],
                            'imagePath'  => [
                                'type'    => 'string',
                                'example' => 'client/img/background/pexels-pixabay-260689.jpg',
                            ],
                            'authorName' => [
                                'type'    => 'string',
                                'example' => 'Pixabay',
                            ],
                            'authorLink' => [
                                'type'    => 'string',
                                'example' => 'https://www.pexels.com/@pixabay',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class BackgroundHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entryPoint = new \Atro\EntryPoints\Background($this->container);
        $entryPoint->setBackground();

        return new JsonResponse($_SESSION['background']);
    }
}
