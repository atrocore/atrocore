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
    summary: 'Get login background image metadata',
    description: 'Returns metadata for the background image displayed on the login screen. '
        . 'Does not require authentication. '
        . 'The result is cached in the session for 2 hours. '
        . 'If custom backgrounds are configured, one is chosen at random; otherwise a built-in image is used.',
    tag: 'Global',
    auth: false,
    responses: [
        200 => [
            'description' => 'Background image metadata.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'imageName'  => [
                                'type'        => 'string',
                                'description' => 'File name of the background image.',
                                'example'     => 'pexels-pixabay-260689.jpg',
                            ],
                            'imagePath'  => [
                                'type'        => 'string',
                                'description' => 'Server-local filesystem path to the image file. Not a public URL.',
                                'example'     => 'public/client/img/background/pexels-pixabay-260689.jpg',
                            ],
                            'authorName' => [
                                'type'        => 'string',
                                'description' => 'Name of the image author. Empty string if not available.',
                                'example'     => 'Pixabay',
                            ],
                            'authorLink' => [
                                'type'        => 'string',
                                'description' => 'URL to the author\'s profile. Empty string if not available.',
                                'example'     => 'https://www.pexels.com/@pixabay',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    hidden: true,
    skipActionHistory: true,
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
