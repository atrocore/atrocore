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

namespace Atro\Seeders;

use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\ParameterType;

class FileStorageSeeder extends AbstractSeeder
{
    public function run(): void
    {
        $this->createDefaultStorage();
        $this->createDefaultFileTypes();
    }

    private function createDefaultStorage(): void
    {
        $qb = $this->getConnection()->createQueryBuilder()
            ->insert('storage')
            ->setValue('id', ':id')
            ->setValue('name', ':name')
            ->setValue('folder_id', ':empty')
            ->setValue('type', ':type')
            ->setValue('path', ':path')
            ->setValue('is_active', ':true')
            ->setValue('created_by_id', ':system')
            ->setParameter('id', '019c320a-707f-735f-becb-3aabd0f0c79c')
            ->setParameter('name', 'Base')
            ->setParameter('empty', '')
            ->setParameter('type', 'local')
            ->setParameter('path', 'upload/files')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('system', 'system');
        try {
            $qb->executeQuery();
        } catch (\Throwable $e) {
        }
    }

    private function createDefaultFileTypes(): void
    {
        $defaults = [
            [
                'id'                  => '019c320b-3b1d-707d-af8c-d011190bd712',
                'name'                => 'Document',
                'extensions'          => ['docx', 'doc', 'odt', 'rtf', 'tex', 'txt', 'pdf']
            ],
            [
                'id'                  => '019c320b-5a35-71f4-bd7e-9673fca98b86',
                'name'                => 'Spreadsheet',
                'extensions'          => ['xlsx', 'xls', 'ods', 'csv', 'tsv']
            ],
            [
                'id'                  => '019c320b-77ba-73d3-8f1b-8346dce0f7bb',
                'name'                => 'Image',
                'extensions'          => ['jpg', 'jpeg', 'gif', 'tiff', 'png', 'bmp', 'webp','avif']
            ],
            [
                'id'                  => '019c320b-8c5f-7374-880c-ce48237046cb',
                'name'                => 'Icon',
                'extensions'          => ['ico', 'png', 'svg']
            ],
            [
                'id'                  => '019c320b-a0e6-7223-8bc4-b4ae7ca63e3c',
                'name'                => 'Audio',
                'extensions'          => ['mp3', 'wav', 'aac', 'flac', 'ogg']
            ],
            [
                'id'                  => '019c320b-b727-70d0-95b1-175ec86ca367',
                'name'                => 'Video',
                'extensions'          => ['mp4', 'avi', 'mkv', 'wmv', 'mov']
            ],
            [
                'id'                  => '019c320b-cccd-7155-a5aa-f154ec2c3f62',
                'name'                => 'Archive',
                'extensions'          => ['zip', 'rar', '7z']
            ],
            [
                'id'                  => '019c320b-e4a1-71be-a909-310f11902d87',
                'name'                => 'Graphics',
                'extensions'          => ['ai', 'svg']
            ],
            [
                'id'                  => '019c320b-fa2b-7365-b8f0-585d6f9dc24f',
                'name'                => 'Presentation',
                'extensions'          => ['pptx', 'ppt', 'ppsx', 'odp', 'key']
            ],
        ];

        foreach ($defaults as $k => $default) {
            $qb = $this->getConnection()->createQueryBuilder()
                ->insert('file_type')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('created_by_id', ':system')
                ->setValue('modified_by_id', ':system')
                ->setValue('extensions', ':extensions')
                ->setParameter('extensions', json_encode($default['extensions']))
                ->setParameter('id', $default['id'])
                ->setParameter('name', $default['name'])
                ->setParameter('system', 'system');
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }
}