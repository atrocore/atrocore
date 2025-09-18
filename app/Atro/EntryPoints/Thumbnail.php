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

class Thumbnail extends AbstractEntryPoint
{
    public function run()
    {
        if (empty($_GET['id']) || empty($_GET['size'])) {
            throw new BadRequest();
        }

        $id = $_GET['id'];
        $size = $_GET['size'];

        /** @var File $file */
        $file = $this->getEntityManager()->getEntity("File", $id);
        if (empty($file)) {
            throw new NotFound();
        }

        $originFilePath = $file->getFilePath();
        $thumbnailPath = $this->getThumbnailCreator()->preparePath($file, $size);

        if (!$this->getThumbnailCreator()->hasThumbnail($file, $size)) {
            if ($this->getThumbnailCreator()->isSvg($file)) {
                $this
                    ->getFileManager()
                    ->putContents('public'.DIRECTORY_SEPARATOR.$thumbnailPath, $file->getContents());
            } else {
                if ($this->getThumbnailCreator()->isPdf($originFilePath)) {
                    $originFilePath = $this->getThumbnailCreator()->createImageFromPdf($file, $originFilePath);
                }

                if (!$this->getThumbnailCreator()->create($originFilePath, $size, $thumbnailPath)) {
                    throw new NotFound();
                }
            }
        }

        header("Location: {$this->getConfig()->getSiteUrl()}/{$thumbnailPath}");
        exit;
    }

    protected function getThumbnailCreator(): ThumbnailCreator
    {
        return $this->container->get(ThumbnailCreator::class);
    }
}
