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

class Avatar extends Injectable
{
    public const string AVATAR_DIR = 'data/upload/avatars';

    public function __construct()
    {
        $this->addDependency('metadata');
        $this->addDependency('user');
    }

    public function upload(string $userId, string $contents, string $name, int $filesize): string
    {
        $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->getAllowedExtensions(), true)) {
            throw new BadRequest("Unsupported avatar file type.");
        }

        $maxSize = $this->convertToBytes((string)ini_get('upload_max_filesize'));
        if (!empty($maxSize) && $filesize > $maxSize) {
            throw new BadRequest("Avatar file exceeds the maximum upload size.");
        }

        $dir = self::getUserDir($userId);
        Util::removeDir($dir);
        Util::createDir($dir);

        $fileName = self::getFileName($userId);

        try {
            (new ImageResize($contents))->save($dir . '/' . $fileName, IMAGETYPE_PNG);
        } catch (ImageResizeException $e) {
            throw new BadRequest("Unsupported avatar file type.");
        } catch (\Throwable $e) {
            throw new BadRequest("Error while saving avatar file: " . $e->getMessage());
        }

        return $fileName;
    }

    public function delete(string $userId): void
    {
        $dir = self::getUserDir($userId);

        try {
            Util::removeDir($dir);
        } catch (\Throwable $e) {
            throw new BadRequest("Error while deleting avatar file: " . $e->getMessage());
        }
    }

    public static function getUserDir(string $userId): string
    {
        return self::AVATAR_DIR . '/' . $userId;
    }

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

    protected function getAllowedExtensions(): array
    {
        return $this->getMetadata()->get(['app', 'file', 'image', 'extensions'], []);
    }

    protected function convertToBytes(string $size): int
    {
        $suffix = substr($size, -1);
        $value = (int)substr($size, 0, -1);

        switch (strtoupper($suffix)) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
                break;
        }

        return $value;
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
