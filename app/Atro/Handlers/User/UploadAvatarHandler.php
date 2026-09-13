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

namespace Atro\Handlers\User;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/User/avatar',
    methods: [
        'POST',
    ],
    summary: 'Upload own avatar',
    description: 'Uploads a raw (multipart/form-data) image as the current user\'s avatar. Bounded only by PHP\'s upload_max_filesize/post_max_size, stored outside the File entity in its own folder, and requires no File ACL permissions.',
    tag: 'User',
    requestBody: [
        'required' => true,
        'content'  => [
            'multipart/form-data' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['file'],
                    'properties' => [
                        'file' => [
                            'type'   => 'string',
                            'format' => 'binary',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Avatar uploaded successfully.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'avatarFileName' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'No file uploaded, upload error, or unsupported file type.',
        ],
    ],
)]
class UploadAvatarHandler extends AbstractHandler
{
    public const AVATAR_DIR       = 'data/upload/avatars';
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uploadedFile = $request->getUploadedFiles()['file'] ?? null;
        if (!$uploadedFile instanceof UploadedFileInterface) {
            throw new BadRequest("No file uploaded.");
        }

        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new BadRequest("File upload failed.");
        }

        $extension = strtolower((string)pathinfo((string)$uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequest("Unsupported avatar file type.");
        }

        if (!is_dir(self::AVATAR_DIR)) {
            mkdir(self::AVATAR_DIR, 0777, true);
        }

        $user = $this->getUser();

        $oldFileName = $user->get('avatarFileName');
        if (!empty($oldFileName)) {
            self::deleteAvatarFiles($oldFileName);
        }

        $fileName = hash('sha256', microtime(true) . random_bytes(16)) . '.' . $extension;

        $uploadedFile->moveTo(self::AVATAR_DIR . '/' . $fileName);

        $user->set('avatarFileName', $fileName);
        $this->getEntityManager()->saveEntity($user);

        return new JsonResponse(['avatarFileName' => $fileName]);
    }

    public static function deleteAvatarFiles(string $fileName): void
    {
        $path = self::AVATAR_DIR . '/' . $fileName;
        if (is_file($path)) {
            @unlink($path);
        }

        foreach (glob(self::AVATAR_DIR . '/.thumbnails/*/' . $fileName) ?: [] as $thumbnailPath) {
            @unlink($thumbnailPath);
        }
    }
}