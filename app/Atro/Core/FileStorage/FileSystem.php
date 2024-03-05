<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\FileStorage;

use Atro\Core\Container;
use Atro\Entities\File;

class FileSystem implements FileStorageInterface
{
    protected Container $container;

    private string $filesPath;
    private string $thumbnailsPath;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->filesPath = trim($this->container->get('config')->get('filesPath', 'upload/files'), '/');
        $this->thumbnailsPath = trim($this->container->get('config')->get('thumbnailsPath', 'upload/thumbnails'), '/');
    }

    public function scan(string $path): void
    {
        $path = trim($path, '/');

        echo '<pre>';
        print_r($path);
        die();
    }

    public function delete(File $file): void
    {
        //@todo
    }

    public function getLocalPath(File $file): string
    {
        //@todo
        return '';
    }

    public function getUrl(File $file): string
    {
        //@todo
        return '';
    }

    public function getThumbnailUrl(File $file, string $type): string
    {
        //@todo
        return '';
    }
}