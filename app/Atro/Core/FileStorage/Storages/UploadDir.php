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

namespace Atro\Core\FileStorage\Storages;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Attachment;
use Espo\EntryPoints\Image;
use Espo\Core\Utils\Util;

class UploadDir extends Base
{
    /**
     * @var array
     */
    protected $dependencyList = ['fileManager', 'filePathBuilder'];

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    public function unlink(Attachment $attachment)
    {
        // remove thumbs
        Util::removeDir($this->getThumbsDirPath($attachment));

        return $this->getFileManager()->unlink($this->getFilePath($attachment));
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    public function isFile(Attachment $attachment)
    {
        return $this->getFileManager()->isFile($this->getFilePath($attachment));
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    public function getContents(Attachment $attachment)
    {
        return $this->getFileManager()->getContents($this->getFilePath($attachment));
    }

    /**
     * @param Attachment $attachment
     * @param            $contents
     *
     * @return mixed
     */
    public function putContents(Attachment $attachment, $contents)
    {
        return $this->getFileManager()->putContents($this->getFilePath($attachment), $contents);
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed|string
     */
    public function getLocalFilePath(Attachment $attachment)
    {
        return $this->getFilePath($attachment);
    }

    /**
     * @inheritDoc
     */
    public function getDownloadUrl(Attachment $attachment): string
    {
        if (!$attachment->isPrivate()) {
            return $this->getFilePath($attachment);
        }

        $url = '?entryPoint=';
        if (in_array($attachment->get('type'), Image::TYPES)) {
            $url .= 'image';
        } else {
            $url .= 'download';
        }
        $url .= "&id={$attachment->get('id')}";

        return $url;
    }

    /**
     * @inheritDoc
     */
    public function getThumbs(Attachment $attachment): array
    {
        // parse name
        $nameParts = explode('.', $attachment->get("name"));

        $ext = array_pop($nameParts);

        // prepare name
        $name = implode('.', $nameParts) . '.png';

        $result = [];
        foreach ($this->getMetadata()->get(['app', 'imageSizes'], []) as $size => $params) {
            $result[$size] = $this->getThumbsDirPath($attachment) . '/' . $size . '/' . $name;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('config');
        $this->addDependency('entityManager');
        $this->addDependency('thumbnail');
        $this->addDependency('metadata');
    }

    /**
     * @param Attachment $attachment
     *
     * @return string
     */
    protected function getFilePath(Attachment $attachment): string
    {
        return $this->getConfig()->get('filesPath', 'upload/files/') . $attachment->getStorageFilePath() . '/' . $attachment->get("name");
    }

    /**
     * @param Attachment $attachment
     *
     * @return string
     */
    protected function getThumbsDirPath(Attachment $attachment): string
    {
        return $this->getConfig()->get('thumbnailsPath', 'upload/thumbnails/') . $attachment->getStorageThumbPath();
    }

    /**
     * @return mixed
     */
    protected function getPathBuilder()
    {
        return $this->getInjection('filePathBuilder');
    }

    /**
     * @return mixed
     */
    protected function getFileManager()
    {
        return $this->getInjection('fileManager');
    }

    /**
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getInjection('config');
    }

    /**
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }
}
