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

namespace Atro\Core\FileStorage;

use Atro\Entities\File;
use Atro\Entities\Folder;
use Atro\Entities\Storage;
use Psr\Http\Message\StreamInterface;

interface FileStorageInterface
{
    public function scan(Storage $storage): void;

    public function create(File $file): bool;

    public function createFolder(Folder $folder): bool;

    /**
     * Create file chunk on storage and return the list of the file chunks
     *
     * @param \stdClass $input
     * @param Storage   $storage
     *
     * @return array
     */
    public function createChunk(\stdClass $input, Storage $storage): array;

    public function deleteCache(Storage $storage): void;

    public function rename(File $file): bool;

    public function renameFolder(Folder $folder): bool;

    public function reupload(File $file): bool;

    public function delete(File $file): bool;

    public function deleteFolder(Folder $folder): bool;

    public function getStream(File $file): StreamInterface;

    public function getUrl(File $file): string;

    public function getThumbnail(File $file, string $size): ?string;

    public function getContents(File $file): string;
}