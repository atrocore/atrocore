<?php

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Random;

class V1Dot13Dot32 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-03-05 16:00:00');
    }

    public function up(): void
    {
        $dirPath = 'data/metadata/clientDefs';

        if (is_dir($dirPath)) {
            foreach (scandir($dirPath) as $file) {
                if (in_array($file, array(".", ".."))) {
                    continue;
                }

                $data = @json_decode(file_get_contents($dirPath . '/' . $file), true);

                if (!empty($data) && is_array($data)) {
                    if (isset($data['iconClass'])) {
                        unset($data['iconClass']);

                        if (!empty($data)) {
                            file_put_contents($dirPath . '/' . $file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        } else {
                            @unlink($dirPath . '/' . $file);
                        }
                    }
                }
            }
        }

        self::createSystemIcons($this->getConnection(), $this->getConfig());
    }

    public static function createSystemIcons(Connection $connection, Config $config)
    {
        $path = self::createFilePath();

        $filesDir = trim($config->get('filesPath', 'upload/files'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
        if (!file_exists($filesDir)) {
            mkdir($filesDir, 0777, true);
        }

        $icons = self::getIcons();

        try {
            foreach (array_chunk(array_keys($icons), 500) as $chunk) {
                $connection
                    ->createQueryBuilder()
                    ->delete('file')
                    ->where('name IN (:names)')
                    ->andWhere('mime_type = :mimeType')
                    ->setParameter('names', $chunk, Connection::PARAM_STR_ARRAY)
                    ->setParameter('mimeType', 'image/svg+xml')
                    ->executeQuery();
            }
        } catch (\Throwable $e) {
        }

        foreach ($icons as $name => $data) {
            $filePath = $filesDir . DIRECTORY_SEPARATOR . $name;

            file_put_contents($filePath, $data['content']);

            $fileMtime = gmdate("Y-m-d H:i:s", filemtime($filePath));
            $hash = md5_file($filePath);

            $date = (new \DateTime())->format('Y-m-d H:i:s');

            try {
                $id = Util::generateUniqueHash();

                $connection
                    ->createQueryBuilder()
                    ->insert('file')
                    ->setValue('id', ':id')
                    ->setValue('name', ':name')
                    ->setValue('mime_type', ':mimeType')
                    ->setValue('file_size', ':fileSize')
                    ->setValue('file_mtime', ':fileMtime')
                    ->setValue('hash', ':hash')
                    ->setValue('path', ':path')
                    ->setValue('thumbnails_path', ':thumbnailsPath')
                    ->setValue('created_at', ':createdAt')
                    ->setValue('created_by_id', ':createdById')
                    ->setValue('modified_at', ':modifiedAt')
                    ->setValue('modified_by_id', ':modifiedById')
                    ->setValue('storage_id', ':storageId')
                    ->setValue('type_id', ':typeId')
                    ->setValue('width', ':width')
                    ->setValue('width_unit_id', ':widthUnitId')
                    ->setValue('height', ':height')
                    ->setValue('height_unit_id', ':heightUnitId')
                    ->setParameter('id', $id)
                    ->setParameter('name', $name)
                    ->setParameter('mimeType', 'image/svg+xml')
                    ->setParameter('fileSize', filesize($filePath))
                    ->setParameter('fileMtime', $fileMtime)
                    ->setParameter('hash', $hash)
                    ->setParameter('path', $path)
                    ->setParameter('thumbnailsPath', $path)
                    ->setParameter('createdAt', $date)
                    ->setParameter('createdById', '1')
                    ->setParameter('modifiedAt', $date)
                    ->setParameter('modifiedById', '1')
                    ->setParameter('storageId', 'a_base')
                    ->setParameter('typeId', 'a_favicon')
                    ->setParameter('width', 24, ParameterType::INTEGER)
                    ->setParameter('widthUnitId', 'pixel')
                    ->setParameter('height', 24, ParameterType::INTEGER)
                    ->setParameter('heightUnitId', 'pixel')
                    ->executeQuery();

                $icons[$name]['imageId'] = $id;
                $icons[$name]['path'] = $filePath;

                unset($icons[$name]['content']);
            } catch (\Throwable $e) {
            }

        }

        $data = [];
        $referencePath = ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'SystemIcon.json';
        if (file_exists($referencePath)) {
            @unlink($referencePath);
        }

        $keys = array_column($icons, 'code');
        $icons = array_combine($keys, $icons);

        file_put_contents($referencePath, json_encode(array_merge($data, $icons), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected static function createFilePath(): string
    {
        $path = [];

        if (file_exists('data/.file-random-path')) {
            $content = json_decode(file_get_contents('data/.file-random-path'), true);

            if (!empty($content)) {
                $path = explode(DIRECTORY_SEPARATOR, $content);
            }
        }

        if (!empty($path)) {
            $path[] = Random::getString(5);
        } else {
            for ($i = 1; $i < 6; $i++) {
                $folderName = Random::getString(5);
                $path[] = $folderName;
            }
            $path[] = Random::getString(5);
        }

        return implode(DIRECTORY_SEPARATOR, $path);
    }

    protected static function getIcons(): array
    {
        $result = [];

        $metadata = self::getIconsMetadata();

        $dir = "vendor/atrocore/core/icons";
        $files = scandir($dir);
        foreach ($files as $file) {
            if (in_array($file, array(".", ".."))) {
                continue;
            }
            $path = "$dir/$file";
            $filename = pathinfo($file,  PATHINFO_FILENAME);
            $extension = pathinfo($file,  PATHINFO_EXTENSION);

            if (file_exists($path) && $extension == 'svg') {
                $result[$file] = [
                    'id'        => $filename,
                    'name'      => ucwords(str_replace('_', ' ', $filename)),
                    'code'      => $filename,
                    'library'   => 'Google',
                    'content'   => file_get_contents($path)
                ];

                if (isset($metadata[$filename])) {
                    $result[$file]['description'] = implode(' ', $metadata[$filename]['tags']);
                } else {
                    $result[$file]['description'] = str_replace('_', '', $filename);
                }
            }
        }

        return $result;
    }

    protected static function getIconsMetadata(): array
    {
        $result = [];

        $path = "vendor/atrocore/core/icons/icons_metadata.json";
        if (file_exists($path)) {
            $data = @json_decode(file_get_contents($path), true);

            if (!empty($data) && is_array($data)) {
                foreach ($data as $item) {
                    $result[$item['name']] = $item;
                }
            }
        }

        return $result;
    }
}
