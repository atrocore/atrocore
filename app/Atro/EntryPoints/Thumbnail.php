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

namespace Atro\EntryPoints;

use Atro\Entities\File;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Utils\Thumbnail as ThumbnailCreator;
use Atro\Core\Utils\Util;

class Thumbnail extends AbstractEntryPoint
{
    public function run()
    {
        if (empty($_GET['id']) || empty($_GET['size'])) {
            throw new BadRequest();
        }

        $id   = $_GET['id'];
        $size = $_GET['size'];

        /** @var File $file */
        $file = $this->getEntityManager()->getEntity("File", $id);
        if (empty($file)) {
            throw new NotFound();
        }

        $tc = $this->getThumbnailCreator();

        if (!$tc->hasThumbnail($file, $size)) {
            if (!$tc->isResizeSupported($file)) {
                // SVG: copy original to thumbnail path using storage abstraction
                $tmpDir = 'data/.thumbnail-tmp' . DIRECTORY_SEPARATOR . $id;
                Util::createDir($tmpDir);
                try {
                    $localPath = $file->findOrCreateLocalFilePath($tmpDir);
                    $target    = 'public' . DIRECTORY_SEPARATOR . $tc->preparePath($file, $size);
                    $this->getFileManager()->mkdir(dirname($target), 0777, true);
                    copy($localPath, $target);
                } finally {
                    Util::removeDir($tmpDir);
                }
            } else {
                $largestSize = $tc->getLargestSizeKey();
                if ($size !== $largestSize && $tc->hasThumbnail($file, $largestSize)) {
                    // Derive smaller size from the already-created large thumbnail
                    if (!$tc->create('public' . DIRECTORY_SEPARATOR . $tc->preparePath($file, $largestSize), $size, $tc->preparePath($file, $size))) {
                        throw new NotFound();
                    }
                } else {
                    // Get original from storage (downloads only for remote storages)
                    $tmpDir = 'data/.thumbnail-tmp' . DIRECTORY_SEPARATOR . $id;
                    Util::createDir($tmpDir);
                    try {
                        $localPath = $file->findOrCreateLocalFilePath($tmpDir);
                        $tc->createLargestThumbnail($file, $localPath, true);
                        if ($size !== $largestSize) {
                            $tc->create('public' . DIRECTORY_SEPARATOR . $tc->preparePath($file, $largestSize), $size, $tc->preparePath($file, $size));
                        }
                    } finally {
                        Util::removeDir($tmpDir);
                    }
                }
            }
        }

        $thumbnailPath = \Atro\Services\File::prepareThumbnailPath($tc->preparePath($file, $size));

        header("Location: /$thumbnailPath");
        exit;
    }

    protected function getThumbnailCreator(): ThumbnailCreator
    {
        return $this->container->get(ThumbnailCreator::class);
    }
}
