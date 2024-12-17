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

namespace Atro\Jobs;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Exception;
use Atro\Entities\Job;
use Atro\Services\File;
use Atro\Core\Utils\Util;

class MassDownload extends AbstractJob implements JobInterface
{
    public const ZIP_TMP_DIR = 'data' . DIRECTORY_SEPARATOR . '.zip-cache';

    public function run(Job $job): void
    {
        $data = $job->getPayload();

        /* @var $service File */
        $service = $this->getServiceFactory()->create('File');
        $files = $this->getEntityManager()->getRepository('File')->find($data['selectParams']);

        if (count($files) === 0) {
            throw new Exception("No Files to download");
        }

        $zip = new \ZipArchive();
        $zipDir = self::ZIP_TMP_DIR . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR . $job->get('id');
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
        Util::removeDir($zipDir);

        $message = sprintf($this->translate('zipDownloadNotification', 'labels', 'File'), $fileData['id']);

        $this->createNotification($job, $message);
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

    public static function clearCache(): void
    {
        Util::removeDir(self::ZIP_TMP_DIR);
    }
}
