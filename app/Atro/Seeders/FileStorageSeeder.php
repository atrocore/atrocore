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
            ->setParameter('id', 'a_base')
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
                'id'                  => 'a_document',
                'name'                => 'Document',
                'assignAutomatically' => true,
                'extensions'          => ['docx', 'doc', 'odt', 'rtf', 'tex', 'txt', 'pdf']
            ],
            [
                'id'                  => 'a_spreadsheet',
                'name'                => 'Spreadsheet',
                'assignAutomatically' => true,
                'extensions'          => ['xlsx', 'xls', 'ods', 'csv', 'tsv']
            ],
            [
                'id'                  => 'a_image',
                'name'                => 'Image',
                'assignAutomatically' => true,
                'extensions'          => ['jpg', 'jpeg', 'gif', 'tiff', 'png', 'bmp', 'webp']
            ],
            [
                'id'                  => 'a_favicon',
                'name'                => 'Icon',
                'assignAutomatically' => true,
                'extensions'          => ['ico', 'png', 'svg']
            ],
            [
                'id'                  => 'a_audio',
                'name'                => 'Audio',
                'assignAutomatically' => true,
                'extensions'          => ['mp3', 'wav', 'aac', 'flac', 'ogg']
            ],
            [
                'id'                  => 'a_video',
                'name'                => 'Video',
                'assignAutomatically' => true,
                'extensions'          => ['mp4', 'avi', 'mkv', 'wmv', 'mov']
            ],
            [
                'id'                  => 'a_archive',
                'name'                => 'Archive',
                'assignAutomatically' => true,
                'extensions'          => ['zip', 'rar', '7z']
            ],
            [
                'id'                  => 'a_graphics',
                'name'                => 'Graphics',
                'assignAutomatically' => true,
                'extensions'          => ['ai', 'svg']
            ],
            [
                'id'                  => 'a_presentation',
                'name'                => 'Presentation',
                'assignAutomatically' => true,
                'extensions'          => ['pptx', 'ppt', 'ppsx', 'odp', 'key']
            ],
        ];

        foreach ($defaults as $k => $default) {
            $qb = $this->getConnection()->createQueryBuilder()
                ->insert('file_type')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('priority', ':priority')
                ->setValue('assign_automatically', ':assignAutomatically')
                ->setValue('created_by_id', ':system')
                ->setValue('modified_by_id', ':system')
                ->setParameter('id', $default['id'])
                ->setParameter('name', $default['name'])
                ->setParameter('priority', $k + 100)
                ->setParameter('assignAutomatically', $default['assignAutomatically'], ParameterType::BOOLEAN)
                ->setParameter('system', 'system');
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }

            $qb1 = $this->getConnection()->createQueryBuilder()
                ->insert('validation_rule')
                ->setValue('id', ':id')
                ->setValue('name', ':type')
                ->setValue('type', ':type')
                ->setValue('is_active', ':true')
                ->setValue('extension', ':extension')
                ->setValue('created_by_id', ':system')
                ->setValue('modified_by_id', ':system')
                ->setValue('file_type_id', ':fileTypeId')
                ->setParameter('id', "v_{$default['id']}")
                ->setParameter('type', 'Extension')
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('extension', json_encode($default['extensions']))
                ->setParameter('system', 'system')
                ->setParameter('fileTypeId', $default['id']);

            try {
                $qb1->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }
}