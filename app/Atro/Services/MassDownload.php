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

namespace Atro\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Exception;
use Espo\Core\Utils\EntityManager;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

class MassDownload extends QueueManagerBase
{
    public const ZIP_TMP_DIR = 'data' . DIRECTORY_SEPARATOR . '.zip-cache';

    public function run(array $data = []): bool
    {
        /* @var $service File */
        $service = $this->getContainer()->get('serviceFactory')->create('File');
        $files = $this->getEntityManager()->getRepository('File')->find($data['selectParams']);

        if (count($files) === 0) {
            throw new Exception("No Files to download");
        }

        $zip = new \ZipArchive();
        $zipDir = self::ZIP_TMP_DIR . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR . $this->qmItem->get('id');
        Util::createDir($zipDir);
        $date = (new \DateTime())->format('Y-m-d H-i-s');
        $name = "download-files-$date.zip";
        $fileName = $zipDir . DIRECTORY_SEPARATOR . $name;
        if ($zip->open($fileName, \ZipArchive::CREATE) !== true) {
            throw new Exception("cannot open archive $fileName\n");
        };

        foreach ($files as $file) {
            $path = $file->findOrCreateLocalFilePath($zipDir);

            if (!file_exists($path)) {
                throw new BadRequest("File '{$path}' does not exist.");
            }

            $zip->addFile($path, basename($path));
        }
        $zip->close();

        $input = new \stdClass();
        $input->name = $name;
        $input->hidden = true;
        $input->folderId = $this->getZipFolderEntity()->get('id');

        $fileData = $service->moveLocalFileToFileEntity($input, $fileName);
        $this->qmItem->_fileId = $fileData['id'];
        Util::removeDir($zipDir);

        return true;
    }

    public function getZipFolderEntity(): \Atro\Entities\Folder
    {
        /** @var \Atro\Repositories\Folder $folderRepo */
        $folderRepo = $this->getEntityManager()->getRepository('Folder');

        $folder = $folderRepo->where(['code' => 'zip_download'])->findOne();
        if (empty($folder)) {
            $folder = $folderRepo->get();
            $folder->set([
                'name'   => 'Zip Download',
                'hidden' => true,
                'code'   => 'zip_download'
            ]);
            $this->getEntityManager()->saveEntity($folder);
        }
        return $folder;
    }

    public function getEntityManager(): \Espo\ORM\EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    public function getNotificationMessage(Entity $queueItem): string
    {
        $message = Parent::getNotificationMessage($queueItem);
        if ($queueItem->get('status') === 'Success') {
            $message .= ' ' . sprintf($this->translate('zipDownloadNotification', 'labels', 'File'), $queueItem->_fileId);
        }
        return $message;
    }

    public static function clearCache(): void
    {
        Util::removeDir(self::ZIP_TMP_DIR);
    }
}
