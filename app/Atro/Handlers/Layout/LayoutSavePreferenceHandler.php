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

namespace Atro\Handlers\Layout;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\LayoutManager;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Layout/action/savePreference',
    methods: ['POST'],
    summary: 'Save layout preference',
    description: 'Saves the user\'s preferred layout profile for a specific layout.',
    tag: 'Layout',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['scope', 'viewType'], 'properties' => ['scope' => ['type' => 'string'], 'viewType' => ['type' => 'string'], 'relatedScope' => ['type' => 'string'], 'layoutProfileId' => ['type' => 'string', 'nullable' => true]]]]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class LayoutSavePreferenceHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->scope) || empty($data->viewType)) {
            throw new BadRequest();
        }

        $relatedEntity   = '';
        $relatedLink     = '';
        $layoutProfileId = null;

        if (!empty($data->relatedScope)) {
            $parts         = explode('.', $data->relatedScope);
            $relatedEntity = $parts[0];
            $relatedLink   = $parts[1] ?? '';
        }

        if (!empty($data->layoutProfileId)) {
            $layoutProfileId = (string) $data->layoutProfileId;
        }

        $this->getLayoutManager()->saveUserPreference(
            (string) $data->scope,
            (string) $data->viewType,
            $relatedEntity,
            $relatedLink,
            $layoutProfileId
        );

        return new BoolResponse(true);
    }

    private function getLayoutManager(): LayoutManager
    {
        return $this->container->get('layoutManager');
    }
}
