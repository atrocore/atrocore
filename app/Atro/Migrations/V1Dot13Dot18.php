<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Config;
use Doctrine\DBAL\Connection;
use Espo\Core\Utils\Random;

class V1Dot13Dot18 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-14 10:00:00');
    }

    public function up(): void
    {
        self::createDefaultSystemIcons($this->getConnection(), $this->getConfig());
    }

    public static function createDefaultSystemIcons(Connection $connection, Config $config): void
    {
        $path = self::createFilePath();

        $filesDir = trim($config->get('filesPath', 'upload/files'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
        if (!file_exists($filesDir)) {
            mkdir($filesDir, 0777, true);
        }

        $icons = self::getDefaultIcons();

        try {
            $connection
                ->createQueryBuilder()
                ->delete('file')
                ->where('name IN (:names)')
                ->setParameter('names', array_column($icons, 'imageName'), Connection::PARAM_STR_ARRAY)
                ->executeQuery();
        } catch (\Throwable $e) {
        }

        foreach ($icons as $k => $icon) {
            $filePath = $filesDir . DIRECTORY_SEPARATOR . $icon['imageName'];

            file_put_contents($filePath, $icon['image']);

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
                    ->setParameter('name', $icon['imageName'])
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
                    ->setParameter('typeId', 'a_image')
                    ->setParameter('width', 123, ParameterType::INTEGER)
                    ->setParameter('widthUnitId', 'pixel')
                    ->setParameter('height', 123, ParameterType::INTEGER)
                    ->setParameter('heightUnitId', 'pixel')
                    ->executeQuery();

                $icons[$k]['imageId'] = $id;
                $icons[$k]['imagePathsData'] = [
                    'thumbnails' => [
                        'small' => $filePath,
                        'medium' => $filePath,
                        'large' => $filePath
                    ]
                ];

                unset($icons[$k]['image']);
            } catch (\Throwable $e) {
            }

        }


        @mkdir(ReferenceData::DIR_PATH);

        $referencePath = ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'SystemIcon.json';
        if (file_exists($referencePath)) {
            @unlink($referencePath);
        }

        $keys = array_column($icons, 'code');
        $icons = array_combine($keys, $icons);

        file_put_contents($referencePath, json_encode($icons, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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

    public static function getDefaultIcons(): array
    {
        return [
            [
                'name'          => 'Letter A',
                'code'          => 'letter_a',
                'description'   => 'a, letter, letter a',
                'imageName'     => 'letter_a.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm53.95 81.81H50.56l-3.05 10.01H28.34l22.87-60.76h20.55l22.77 60.76H74.88l-3.04-10.01zm-3.98-13.16l-6.63-21.84-6.66 21.84h13.29z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter B',
                'code'          => 'letter_b',
                'description'   => 'b, letter, letter b',
                'imageName'     => 'letter_b.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm16.65 31.06h35.17c5.86 0 10.35 1.46 13.49 4.36 3.14 2.9 4.71 6.49 4.71 10.78 0 3.59-1.12 6.68-3.37 9.24-1.49 1.73-3.68 3.07-6.55 4.07 4.37 1.05 7.57 2.84 9.63 5.4 2.05 2.56 3.08 5.77 3.08 9.63 0 3.15-.74 5.98-2.19 8.5-1.47 2.52-3.48 4.5-6.01 5.97-1.58.91-3.96 1.57-7.14 1.98-4.23.55-7.03.83-8.42.83h-32.4V31.06zm18.92 23.85h8.19c2.93 0 4.98-.51 6.12-1.51 1.15-1.02 1.73-2.47 1.73-4.38 0-1.77-.58-3.15-1.73-4.14-1.15-1-3.15-1.5-6-1.5h-8.32v11.53h.01zm0 23.84h9.57c3.24 0 5.52-.58 6.85-1.73s1.99-2.67 1.99-4.61c0-1.8-.65-3.24-1.97-4.33-1.32-1.09-3.62-1.64-6.92-1.64h-9.53v12.31h.01z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter C',
                'code'          => 'letter_c',
                'description'   => 'c, letter, letter c',
                'imageName'     => 'letter_c.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm57.04 66.96l16.46 4.96c-1.1 4.61-2.84 8.47-5.23 11.56-2.38 3.1-5.32 5.43-8.85 7-3.52 1.57-8.01 2.36-13.45 2.36-6.62 0-12.01-.96-16.21-2.87-4.19-1.92-7.79-5.3-10.83-10.13-3.04-4.82-4.57-11.02-4.57-18.54 0-10.04 2.67-17.76 8.02-23.17 5.36-5.39 12.93-8.09 22.71-8.09 7.65 0 13.68 1.54 18.06 4.64 4.37 3.1 7.64 7.85 9.76 14.27l-16.55 3.66c-.58-1.84-1.19-3.18-1.82-4.03-1.06-1.43-2.35-2.53-3.86-3.3-1.53-.78-3.22-1.16-5.11-1.16-4.27 0-7.54 1.71-9.8 5.12-1.71 2.53-2.57 6.52-2.57 11.94 0 6.73 1.02 11.33 3.07 13.83 2.05 2.49 4.92 3.73 8.63 3.73 3.59 0 6.31-1 8.15-3.03 1.83-1.99 3.16-4.92 3.99-8.75z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter D',
                'code'          => 'letter_d',
                'description'   => 'd, letter, letter d',
                'imageName'     => 'letter_d.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm16.82 31.06h27.91c5.49 0 9.94.75 13.32 2.23 3.38 1.5 6.18 3.63 8.4 6.42 2.21 2.8 3.8 6.04 4.79 9.74 1 3.71 1.5 7.62 1.5 11.77 0 6.49-.74 11.53-2.22 15.11-1.47 3.58-3.52 6.58-6.15 9-2.63 2.42-5.45 4.03-8.46 4.84-4.12 1.1-7.85 1.65-11.19 1.65h-27.9V31.06zm18.75 13.75v33.18h4.61c3.93 0 6.73-.44 8.4-1.3 1.65-.88 2.96-2.39 3.9-4.55.95-2.18 1.41-5.69 1.41-10.55 0-6.44-1.05-10.83-3.15-13.21-2.11-2.38-5.6-3.56-10.48-3.56h-4.69v-.01z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter E',
                'code'          => 'letter_e',
                'description'   => 'e, letter, letter e',
                'imageName'     => 'letter_e.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm16.56 31.06h50.24v12.98h-31.4v9.67H82.4V66.1H53.29v11.97h32.33v13.75H34.45V31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter F',
                'code'          => 'letter_f',
                'description'   => 'f, letter, letter f',
                'imageName'     => 'letter_f.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm20.34 31.06h46.42v13.07H57.07v10.61h23.59v12.3H57.07v24.78H38.23V31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter G',
                'code'          => 'letter_g',
                'description'   => 'g, letter, letter g',
                'imageName'     => 'letter_g.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm45.46 69.84V57.2h29.02v25.84c-5.56 3.79-10.47 6.38-14.72 7.75-4.27 1.37-9.33 2.05-15.19 2.05-7.21 0-13.1-1.23-17.64-3.69-4.54-2.45-8.06-6.11-10.57-10.98-2.5-4.85-3.75-10.44-3.75-16.73 0-6.63 1.37-12.39 4.1-17.3 2.73-4.89 6.73-8.61 12.01-11.16 4.12-1.97 9.66-2.94 16.62-2.94 6.7 0 11.72.61 15.05 1.82 3.34 1.22 6.1 3.1 8.29 5.66 2.19 2.56 3.85 5.8 4.95 9.72l-18.08 3.25c-.75-2.31-2.01-4.07-3.79-5.29-1.78-1.23-4.05-1.84-6.82-1.84-4.1 0-7.38 1.43-9.83 4.29-2.45 2.86-3.66 7.38-3.66 13.56 0 6.56 1.23 11.26 3.71 14.07 2.46 2.81 5.91 4.23 10.32 4.23 2.09 0 4.09-.3 6-.91 1.89-.61 4.07-1.64 6.53-3.08v-5.69H63.35v.01z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter H',
                'code'          => 'letter_h',
                'description'   => 'h, letter, letter h',
                'imageName'     => 'letter_h.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm14.49 31.06h18.75v21.22h20.54V31.06H90.5v60.76H71.67V67.21H51.13v24.61H32.38V31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter I',
                'code'          => 'letter_i',
                'description'   => 'i, letter, letter i',
                'imageName'     => 'letter_i.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm34.13 31.06h18.84v60.76H52.02V31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter J',
                'code'          => 'letter_j',
                'description'   => 'j, letter, letter j',
                'imageName'     => 'letter_j.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm49.15 30.55h18.84v33c0 6.93-.61 12.21-1.84 15.83-1.23 3.61-3.71 6.68-7.43 9.19-3.72 2.5-8.49 3.76-14.28 3.76-6.14 0-10.89-.83-14.26-2.49-3.38-1.65-5.98-4.07-7.82-7.27-1.84-3.18-2.93-7.13-3.25-11.82l17.91-2.43c.03 2.66.27 4.64.71 5.93.44 1.3 1.19 2.33 2.25 3.14.72.52 1.74.78 3.07.78 2.11 0 3.65-.78 4.64-2.33.98-1.56 1.47-4.19 1.47-7.88V30.55h-.01z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter K',
                'code'          => 'letter_k',
                'description'   => 'k, letter, letter k',
                'imageName'     => 'letter_k.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm11.35 31.06h18.75v22.95l19.67-22.95h24.96L70.45 53.89l23.2 37.93h-23.1L57.73 66.87l-9.74 10.15v14.79H29.24V31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter L',
                'code'          => 'letter_l',
                'description'   => 'l, letter, letter l',
                'imageName'     => 'letter_l.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm19.49 31.06h18.75v45.82H85.5v14.94H37.38V31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter M',
                'code'          => 'letter_m',
                'description'   => 'm, letter, letter m',
                'imageName'     => 'letter_m.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm9.39 31.06h24.77l9.43 36.97 9.46-36.97H95.6v60.76H80.24V45.5L68.38 91.82H54.47L42.64 45.5v46.32H27.28V31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter N',
                'code'          => 'letter_n',
                'description'   => 'n, letter, letter n',
                'imageName'     => 'letter_n.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm14.53 31.06h17.52l22.79 33.55V31.06h17.74v60.76H72.73L50.07 58.46v33.36H32.42V31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter O',
                'code'          => 'letter_o',
                'description'   => 'o, letter, letter o',
                'imageName'     => 'letter_o.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm12.07 61.48c0-9.91 2.76-17.64 8.29-23.15 5.53-5.53 13.22-8.29 23.1-8.29 10.11 0 17.91 2.72 23.38 8.13 5.47 5.43 8.2 13.04 8.2 22.81 0 7.1-1.19 12.91-3.58 17.45-2.39 4.54-5.86 8.08-10.37 10.61-4.53 2.53-10.16 3.79-16.9 3.79-6.85 0-12.52-1.09-17.01-3.27-4.48-2.19-8.13-5.64-10.92-10.37-2.79-4.7-4.19-10.61-4.19-17.71zm18.75.04c0 6.12 1.15 10.54 3.42 13.21 2.29 2.67 5.4 4.02 9.33 4.02 4.03 0 7.17-1.32 9.38-3.93 2.22-2.63 3.32-7.33 3.32-14.13 0-5.71-1.16-9.89-3.47-12.52-2.32-2.64-5.45-3.96-9.41-3.96-3.79 0-6.85 1.34-9.14 4.02-2.28 2.67-3.43 7.11-3.43 13.29z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter P',
                'code'          => 'letter_p',
                'description'   => 'p, letter, letter p',
                'imageName'     => 'letter_p.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm17.75 31.06h31.23c6.8 0 11.89 1.61 15.29 4.85 3.38 3.24 5.08 7.85 5.08 13.83 0 6.14-1.85 10.95-5.54 14.4-3.69 3.47-9.33 5.19-16.92 5.19h-10.3v22.49H35.64V31.06zm18.84 25.97h4.62c3.65 0 6.21-.64 7.68-1.9 1.47-1.26 2.21-2.87 2.21-4.84 0-1.91-.64-3.52-1.92-4.85-1.27-1.33-3.68-1.99-7.21-1.99h-5.37v13.58h-.01z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter Q',
                'code'          => 'letter_q',
                'description'   => 'q, letter, letter q',
                'imageName'     => 'letter_q.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm67.27 80.02c2.33 1.63 3.86 2.66 4.58 3.07 1.06.61 2.52 1.32 4.33 2.12l-5.19 10.51a59.624 59.624 0 01-7.78-4.53c-2.57-1.75-4.37-3.07-5.39-3.95-4.14 1.8-9.33 2.7-15.59 2.7-9.24 0-16.52-2.4-21.85-7.21-6.31-5.69-9.46-13.68-9.46-23.97 0-10 2.76-17.76 8.27-23.31 5.52-5.53 13.21-8.3 23.11-8.3 10.08 0 17.86 2.7 23.35 8.12 5.49 5.4 8.23 13.15 8.23 23.22.01 8.97-2.2 16.15-6.61 21.53zm-14.39-9.63c1.5-2.67 2.25-6.68 2.25-12.01 0-6.12-1.15-10.49-3.42-13.13-2.29-2.62-5.43-3.93-9.45-3.93-3.75 0-6.78 1.34-9.09 4.02-2.33 2.67-3.49 6.86-3.49 12.55 0 6.63 1.13 11.29 3.39 13.96 2.28 2.67 5.39 4.02 9.33 4.02 1.27 0 2.48-.13 3.61-.37-1.58-1.53-4.07-2.96-7.48-4.31l2.94-6.75c1.67.3 2.97.68 3.89 1.12.93.44 2.74 1.6 5.45 3.48.63.43 1.32.89 2.07 1.35z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter R',
                'code'          => 'letter_r',
                'description'   => 'r, letter, letter r',
                'imageName'     => 'letter_r.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm13.68 91.82V31.06h31.29c5.8 0 10.24.49 13.29 1.5 3.07.99 5.54 2.84 7.43 5.53 1.88 2.7 2.81 6 2.81 9.87 0 3.37-.72 6.28-2.16 8.73-1.43 2.46-3.41 4.44-5.94 5.97-1.6.96-3.8 1.77-6.59 2.4 2.23.75 3.86 1.49 4.88 2.23.68.49 1.68 1.57 3 3.2 1.3 1.63 2.18 2.89 2.62 3.78l9.12 17.55H70.1L60.07 73.29c-1.27-2.4-2.4-3.96-3.39-4.68-1.36-.93-2.9-1.4-4.61-1.4h-1.65v24.61H31.57zm18.84-36.07h7.93c.85 0 2.52-.28 4.98-.83 1.24-.24 2.26-.88 3.04-1.91.79-1.03 1.19-2.21 1.19-3.54 0-1.97-.62-3.48-1.87-4.53-1.24-1.06-3.58-1.58-7.02-1.58H50.4v12.39h.01z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter S',
                'code'          => 'letter_s',
                'description'   => 's, letter, letter s',
                'imageName'     => 'letter_s.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zM33.9 71.72l17.82-1.12c.38 2.89 1.17 5.09 2.36 6.59 1.94 2.45 4.7 3.68 8.29 3.68 2.67 0 4.74-.62 6.18-1.88 1.46-1.26 2.18-2.72 2.18-4.37 0-1.57-.68-2.98-2.05-4.23-1.37-1.24-4.57-2.4-9.59-3.52-8.23-1.84-14.09-4.3-17.59-7.37-3.54-3.05-5.3-6.96-5.3-11.71 0-3.11.91-6.05 2.72-8.83 1.81-2.79 4.53-4.96 8.16-6.55 3.63-1.58 8.61-2.38 14.94-2.38 7.76 0 13.68 1.44 17.75 4.34 4.07 2.89 6.49 7.48 7.27 13.79l-17.65 1.05c-.47-2.76-1.46-4.77-2.96-6.01-1.51-1.26-3.59-1.88-6.24-1.88-2.18 0-3.83.47-4.94 1.39-1.1.92-1.65 2.05-1.65 3.38 0 .96.45 1.82 1.34 2.6.86.81 2.96 1.54 6.27 2.23 8.2 1.77 14.07 3.56 17.61 5.37 3.55 1.81 6.14 4.04 7.75 6.73 1.61 2.67 2.42 5.67 2.42 9 0 3.89-1.07 7.48-3.22 10.78-2.16 3.28-5.16 5.78-9.04 7.48-3.86 1.7-8.73 2.55-14.61 2.55-10.32 0-17.48-1.99-21.46-5.97-3.99-3.96-6.23-9.01-6.76-15.14z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter T',
                'code'          => 'letter_t',
                'description'   => 't, letter, letter t',
                'imageName'     => 'letter_t.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm14.99 31.06H90v15.02H70.82v45.74H52.06V46.08H32.88V31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter U',
                'code'          => 'letter_u',
                'description'   => 'u, letter, letter u',
                'imageName'     => 'letter_u.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm53.9 30.55h18.75v36.19c0 3.58-.57 6.97-1.68 10.16-1.12 3.2-2.87 5.98-5.26 8.37-2.39 2.39-4.89 4.06-7.51 5.04-3.65 1.34-8.03 2.02-13.14 2.02-2.96 0-6.18-.21-9.67-.62-3.49-.41-6.42-1.23-8.77-2.46-2.35-1.23-4.5-2.97-6.44-5.23-1.95-2.26-3.28-4.6-4-7-1.16-3.86-1.74-7.28-1.74-10.27v-36.2h18.75v37.06c0 3.31.92 5.9 2.74 7.75 1.84 1.87 4.38 2.8 7.64 2.8 3.21 0 5.74-.92 7.58-2.76 1.82-1.82 2.74-4.43 2.74-7.79V30.55h.01z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter V',
                'code'          => 'letter_v',
                'description'   => 'v, letter, letter v',
                'imageName'     => 'letter_v.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm10.62 31.06h19.62l13.69 43.73 13.49-43.73h19.05L71.81 91.82H51.47L28.51 31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter W',
                'code'          => 'letter_w',
                'description'   => 'w, letter, letter w',
                'imageName'     => 'letter_w.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm1.08 31.06h17.82l6.41 33.9 9.39-33.9h17.68l9.46 33.96 6.45-33.96h17.74l-13.4 60.76H72.1L61.44 53.56 50.85 91.82H32.46L18.97 31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter X',
                'code'          => 'letter_x',
                'description'   => 'x, letter, letter x',
                'imageName'     => 'letter_x.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zM30.3 31.06H51l10.76 18.7 10.45-18.7h20.45L73.79 60.49l20.66 31.33H73.32L61.4 72.3 49.39 91.82H28.43l20.95-31.67L30.3 31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter Y',
                'code'          => 'letter_y',
                'description'   => 'y, letter, letter y',
                'imageName'     => 'letter_y.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm10.5 31.06h20.85l12.29 20.45 12.23-20.45h20.73L70.9 66.38v25.44H52.06V66.38L28.39 31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
            [
                'name'          => 'Letter Z',
                'code'          => 'letter_z',
                'description'   => 'z, letter, letter z',
                'imageName'     => 'letter_z.svg',
                'image'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><path d="M17.89 0h88.9c8.85 0 16.1 7.24 16.1 16.1v90.68c0 8.85-7.24 16.1-16.1 16.1H16.1c-8.85 0-16.1-7.24-16.1-16.1v-88.9C0 8.05 8.05 0 17.89 0zm18.05 31.06h53.04v12.15L54.92 78.75h35.33v13.07H32.63V79.2l33.7-35.16H35.94V31.06z" fill-rule="evenodd" clip-rule="evenodd"/></svg>'
            ],
        ];
    }
}
