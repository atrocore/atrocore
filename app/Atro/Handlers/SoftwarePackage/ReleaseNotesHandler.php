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
    path: '/SoftwarePackage/{id}/releaseNotes',
    methods: [
        'POST',
    ],
    summary: 'Get release notes of a software package',
    description: 'Returns the release notes of the specified software package. Accessible by administrators only.',
    tag: 'SoftwarePackage',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the software package to get the release notes of.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Release notes of the software package.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'html' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Current user is not an administrator.',
        ],
        404 => [
            'description' => 'Software package not found.',
        ],
    ],
)]
class ReleaseNotesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return new JsonResponse([
            'html' => $this->getRecordService('SoftwarePackage')->getReleaseNotes((string)$request->getAttribute('id'))
        ]);
    }
}
