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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Base;

class File extends Base
{
    protected $entityType = "File";

    public function getDownloadUrl(): string
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getDownloadUrl($this);
    }

    public function getSmallThumbnailUrl(): string
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getSmallThumbnailUrl($this);
    }

    public function getMediumThumbnailUrl(): string
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getMediumThumbnailUrl($this);
    }

    public function getLargeThumbnailUrl(): string
    {
        return $this->getEntityManager()->getRepository($this->entityType)->getLargeThumbnailUrl($this);
    }
}
