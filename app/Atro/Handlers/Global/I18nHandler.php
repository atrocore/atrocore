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
    path: '/i18n',
    methods: [
        'GET',
    ],
    summary: 'Get UI translation labels',
    description: 'Returns the full translation tree for the requested locale. '
        . 'The response is a nested object keyed first by scope (entity name or `Global`), '
        . 'then by category (`fields`, `labels`, `messages`, `options`, etc.), '
        . 'then by translation key. '
        . 'Does not require authentication — it is loaded before login to render the login page. '
        . 'The client caches the result and re-fetches only when the data timestamp changes. '
        . 'If neither `locale` nor `default` is provided, the locale configured for the current user (or the system default) is used.',
    tag: 'Global',
    auth: false,
    parameters: [
        [
            'name'        => 'locale',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Locale ID to return translations for (e.g. `de_DE`, `fr_FR`). '
                . 'Overrides the user\'s configured locale for this request.',
            'schema'      => [
                'type'    => 'string',
                'example' => 'de_DE',
            ],
        ],
        [
            'name'        => 'default',
            'in'          => 'query',
            'required'    => false,
            'description' => 'When `true`, returns translations for the system default language, '
                . 'ignoring the user\'s locale. Used by the admin UI to display default-language labels.',
            'schema'      => [
                'type'    => 'boolean',
                'example' => true,
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Translation tree for the requested locale.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'        => 'object',
                        'description' => 'Keys are scope names (`Global`, entity names). '
                            . 'Each scope contains category keys (`fields`, `labels`, `messages`, `options`, etc.) '
                            . 'with string translation values.',
                        'example'     => [
                            'Global'  => [
                                'labels'   => ['Save' => 'Save', 'Cancel' => 'Cancel'],
                                'messages' => ['confirmRemove' => 'Are you sure?'],
                            ],
                            'Product' => [
                                'fields'  => ['name' => 'Name', 'isActive' => 'Active'],
                                'options' => ['status' => ['Draft' => 'Draft', 'Published' => 'Published']],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    hidden: false,
    skipActionHistory: true,
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
