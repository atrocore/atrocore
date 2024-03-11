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

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Entities\File as FileEntity;
use Atro\Core\Templates\Repositories\Base;

class File extends Base
{
    public function getDownloadUrl(FileEntity $file): string
    {
        return $this->getStorage($file)->getUrl($file);
    }

    public function getSmallThumbnailUrl(FileEntity $file): string
    {
        return $this->getStorage($file)->getThumbnailUrl($file, 'small');
    }

    public function getMediumThumbnailUrl(FileEntity $file): string
    {
        return $this->getStorage($file)->getThumbnailUrl($file, 'medium');
    }

    public function getLargeThumbnailUrl(FileEntity $file): string
    {
        return $this->getStorage($file)->getThumbnailUrl($file, 'large');
    }

    public function getStorage(FileEntity $file): FileStorageInterface
    {
        return $this->getInjection('container')->get($file->get('storage')->get('type') . 'Storage');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
