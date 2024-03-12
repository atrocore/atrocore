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
use Espo\Core\FilePathBuilder;
use Espo\ORM\Entity;
use Atro\Core\Utils\Thumbnail;

class File extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (empty($entity->get('thumbnailsPath'))) {
            if (!empty($entity->get('path'))) {
                $entity->set('thumbnailsPath', $entity->get('path'));
            } else {
                $thumbnailsDirPath = trim($this->getConfig()->get('thumbnailsPath', 'upload/thumbnails'), '/');
                $entity->set('thumbnailsPath', $this->getPathBuilder()->createPath($thumbnailsDirPath . '/'));
            }
        }
    }

    public function getDownloadUrl(FileEntity $file): string
    {
        return $this->getStorage($file)->getUrl($file);
    }

    public function getSmallThumbnailUrl(FileEntity $file): ?string
    {
        return $this->getThumbnail()->getPath($file, 'small');
    }

    public function getMediumThumbnailUrl(FileEntity $file): ?string
    {
        return $this->getThumbnail()->getPath($file, 'medium');
    }

    public function getLargeThumbnailUrl(FileEntity $file): ?string
    {
        return $this->getThumbnail()->getPath($file, 'large');
    }

    public function getStorage(FileEntity $file): FileStorageInterface
    {
        return $this->getInjection('container')->get($file->get('storage')->get('type') . 'Storage');
    }

    protected function getPathBuilder(): FilePathBuilder
    {
        return $this->getInjection('container')->get('filePathBuilder');
    }

    protected function getThumbnail(): Thumbnail
    {
        return $this->getInjection('container')->get(Thumbnail::class);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
