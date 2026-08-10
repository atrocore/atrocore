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

namespace Atro\Handlers\SoftwarePackage;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/SoftwarePackage/lastUpdate',
    methods: [
        'GET',
    ],
    summary: 'Get the status of the last system update',
    description: 'Returns the status of the last system update ready to be displayed in the list view. Accessible by administrators only.',
    tag: 'SoftwarePackage',
    responses: [
        200 => [
            'description' => 'Status of the last system update.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'label'   => [
                                'type'    => 'string',
                                'example' => 'Last Update',
                            ],
                            'value'   => [
                                'type'    => 'string',
                                'example' => 'Success',
                            ],
                            'style'   => [
                                'type' => 'string',
                                'enum' => [
                                    'success',
                                    'danger',
                                    'warning',
                                    'info',
                                ],
                            ],
                            'details' => [
                                'type'     => 'string',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Current user is not an administrator.',
        ],
    ],
)]
class LastUpdateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        // @todo replace the stub with the real status of the last system update.
        //       Possible sources: SoftwarePackage::CHECK_UP_FILE (data/composer-check-up.log),
        //       data/composer.log, Atro\Jobs\ComposerAutoUpdate.
        return new JsonResponse([
            'label'   => $this->getLanguage()->translate('lastUpdate', 'labels', 'SoftwarePackage'),
            'value'   => $this->getLanguage()->translate('Success', 'lastUpdateStatuses', 'SoftwarePackage'),
            'style'   => 'success',
            'details' => 'Some text',
        ]);
    }
}
