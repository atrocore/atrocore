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
use Atro\Core\Utils\DataUtil;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/I18n',
    methods: [
        'GET',
    ],
    summary: 'Returns all translation labels for the UI',
    description: 'Returns all translation labels for the UI. Does not require authentication.',
    tag: 'Global',
    auth: false,
    parameters: [
        [
            'name'     => 'locale',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'string',
                'example' => 'de_DE',
            ],
        ],
        [
            'name'     => 'default',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'boolean',
                'example' => false,
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Translation labels',
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
class I18nHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        if (!empty($qp['locale'])) {
            $this->getLanguage()->setLocale($qp['locale']);
        }

        $data = !empty($qp['default'])
            ? $this->container->get('defaultLanguage')->getAll()
            : $this->getLanguage()->getAll();

        return new JsonResponse(DataUtil::toArray($data));
    }
}
