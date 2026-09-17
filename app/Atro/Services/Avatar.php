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
use Atro\Core\Utils\Util;
use Atro\Entities\User;
use Espo\Core\Injectable;
use Gumlet\ImageResize;
use Gumlet\ImageResizeException;
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

    public function upload(UploadedFileInterface $uploadedFile): string
    {
        $extension = strtolower((string)pathinfo((string)$uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, $this->getAllowedExtensions(), true)) {
            throw new BadRequest("Unsupported avatar file type.");
        }

        $sourcePath = $uploadedFile->getStream()->getMetadata('uri');
        if (empty($sourcePath) || !is_file($sourcePath)) {
            throw new BadRequest("File upload failed.");
        }

        $userId = $this->getUser()->get('id');

        $dir = self::getUserDir($userId);
        Util::removeDir($dir);
        Util::createDir($dir);

        $fileName = self::getFileName($userId);

        try {
            (new ImageResize($sourcePath))->save($dir . '/' . $fileName, IMAGETYPE_PNG);
        } catch (ImageResizeException $e) {
            throw new BadRequest("Unsupported avatar file type.");
        }

        return $fileName;
    }

    public function delete(): void
    {
        $dir = self::getUserDir($this->getUser()->get('id'));

        Util::removeDir($dir);
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
        return md5($userId) . '.png';
    }

    public static function getFullPath(string $userId): string
    {
        return self::getUserDir($userId) . '/' . self::getFileName($userId);
    }

    public static function isUploaded(string $userId): bool
    {
        return file_exists(self::getFullPath($userId));
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
