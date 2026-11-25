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

use Atro\Core\Utils\Thumbnail;
use Atro\Entities\File;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;

class Image extends AbstractEntryPoint
{
    public const TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];

    public function run()
    {
        if (empty($_GET['id'])) {
            throw new BadRequest();
        }

        /** @var File $file */
        $file = $this->getEntityManager()->getEntity("File", $_GET['id']);
        if (empty($file)) {
            throw new NotFound();
        }

        $this->show($file, $_GET['size'] ?? null);
    }

    protected function show(File $file, ?string $size = null): void
    {
        if (!$this->checkFile($file)) {
            throw new Forbidden();
        }

        $fileType = $file->get('mimeType');
        if (!in_array($fileType, static::TYPES)) {
            throw new Error();
        }

        if (!empty($size)) {
            if (empty($this->getImageSize($size))) {
                throw new NotFound();
            }

            $url = $this->container->get(Thumbnail::class)->getUrl($file, $size);
            if ($url === null) {
                throw new NotFound();
            }
            header("Location: /$url");
            exit;
        } else {
            $contents = $file->getContents();
        }

        header('Content-Disposition:inline;filename="' . $file->get('name') . '"');
        if (!empty($fileType)) {
            header('Content-Type: ' . $fileType);
        }
        header('Cache-Control: private, max-age=360000, must-revalidate');
        $fileSize = mb_strlen($contents, "8bit");
        if ($fileSize) {
            header('Content-Length: ' . $fileSize);
        }
        echo $contents;
        exit;
    }

    protected function checkFile(File $file): bool
    {
        return $this->getAcl()->checkEntity($file);
    }

    protected function getImageSize(string $type): ?array
    {
        return $this->getMetadata()->get("app.thumbnailTypes.$type.size");
    }
}
