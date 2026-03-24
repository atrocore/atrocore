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

namespace Atro\Handlers\File;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/File/action/prepareForRichEditor',
    methods: ['POST'],
    summary: 'Prepare file for rich editor',
    description: 'Creates a public sharing link for a file to be used in a rich text editor.',
    tag: 'File',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['fileId'], 'properties' => ['fileId' => ['type' => 'string']]]]],
    ],
    responses: [
        200 => ['description' => 'File info with shared URL', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
class FilePrepareForRichEditorHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->fileId)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check('File', 'read')) {
            throw new Forbidden();
        }

        $em     = $this->getEntityManager();
        $file   = $em->getEntity('File', $data->fileId);
        $result = $file->getValueMap();

        $sharingRepo = $em->getRepository('Sharing');
        $sharing     = $sharingRepo->get();
        $sharing->set('fileId', $file->get('id'));
        $em->saveEntity($sharing);
        $this->getRecordService('Sharing')->prepareEntityForOutput($sharing);
        $result->sharedUrl = $sharing->get('link');

        return new JsonResponse((array) $result);
    }
}
