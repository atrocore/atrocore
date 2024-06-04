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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Base;

class File extends Base
{
    protected $entityType = "File";

    protected ?Storage $storage = null;

    public function getStorage(): Storage
    {
        if ($this->storage === null) {
            $this->storage = $this->getEntityManager()->getRepository('Storage')->get($this->get('storageId'));
        }

        return $this->storage;
    }

    public function getContents(): string
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getContents($this);
    }

    public function getFilePath(): string
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getFilePath($this);
    }

    public function getDownloadUrl(): ?string
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getDownloadUrl($this);
    }

    public function getSmallThumbnailUrl(): ?string
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getSmallThumbnailUrl($this);
    }

    public function getMediumThumbnailUrl(): ?string
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getMediumThumbnailUrl($this);
    }

    public function getLargeThumbnailUrl(): ?string
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getLargeThumbnailUrl($this);
    }

    public function getPathsData(): array
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getPathsData($this);
    }
}
