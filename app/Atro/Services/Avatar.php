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

namespace Atro\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Utils\Metadata;
use Atro\Entities\User;
use Espo\Core\Injectable;
use Psr\Http\Message\UploadedFileInterface;

class Avatar extends Injectable
{
    public const string AVATAR_DIR = 'data/upload/avatars';

    public function __construct()
    {
        $this->addDependency('metadata');
        $this->addDependency('user');
    }

    protected function getAllowedExtensions(): array
    {
        return $this->getMetadata()->get(['app', 'file', 'image', 'extensions'], []);
    }

    /**
     * Stores the uploaded file as raw, ACL-free avatar storage (no `File` entity involved),
     * inside a folder dedicated to the user, and returns its generated file name. Saving it
     * onto the user is the caller's responsibility.
     */
    public function upload( UploadedFileInterface $uploadedFile): string
    {
        $extension = strtolower((string)pathinfo((string)$uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, $this->getAllowedExtensions(), true)) {
            throw new BadRequest("Unsupported avatar file type.");
        }

        $dir = self::getUserDir($this->getUser()->get('id'));

        // wipe any previous avatar before storing the new one
        self::removeDir($dir);

        mkdir($dir, 0777, true);

        // a distinct name per upload keeps the browser from serving a stale cached image
        // when re-uploading with the same extension (the entry point sets a long max-age)
        $fileName = 'avatar-' . bin2hex(random_bytes(4)) . '.' . $extension;

        $uploadedFile->moveTo($dir . '/' . $fileName);

        return $fileName;
    }

    public static function getUserDir(string $userId): string
    {
        return self::AVATAR_DIR . '/' . $userId;
    }

    /**
     * The `avatar` field is notStorable, so the currently stored file name is discovered
     * from disk (a user's folder holds at most one avatar file) rather than tracked in the DB.
     */
    public static function getFileName(string $userId): ?string
    {
        $dir = self::getUserDir($userId);
        if (!is_dir($dir)) {
            return null;
        }

        foreach (scandir($dir) as $item) {
            if (is_file($dir . '/' . $item)) {
                return $item;
            }
        }

        return null;
    }

    public static function getPath(string $userId, string $fileName): string
    {
        return self::getUserDir($userId) . '/' . $fileName;
    }

    /**
     * Deletes the user's avatar folder along with the stored file.
     */
    public static function deleteFiles(string $userId): void
    {
        self::removeDir(self::getUserDir($userId));
    }

    protected static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }

    protected function getUser(): User
    {
        return $this->getInjection('user');
    }
}