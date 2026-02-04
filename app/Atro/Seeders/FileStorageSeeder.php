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
            ->setParameter('id', $this->getIdGenerator()->toUuid('a_base'))
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
                'extensions'          => ['docx', 'doc', 'odt', 'rtf', 'tex', 'txt', 'pdf']
            ],
            [
                'id'                  => 'a_spreadsheet',
                'name'                => 'Spreadsheet',
                'extensions'          => ['xlsx', 'xls', 'ods', 'csv', 'tsv']
            ],
            [
                'id'                  => 'a_image',
                'name'                => 'Image',
                'extensions'          => ['jpg', 'jpeg', 'gif', 'tiff', 'png', 'bmp', 'webp','avif']
            ],
            [
                'id'                  => 'a_favicon',
                'name'                => 'Icon',
                'extensions'          => ['ico', 'png', 'svg']
            ],
            [
                'id'                  => 'a_audio',
                'name'                => 'Audio',
                'extensions'          => ['mp3', 'wav', 'aac', 'flac', 'ogg']
            ],
            [
                'id'                  => 'a_video',
                'name'                => 'Video',
                'extensions'          => ['mp4', 'avi', 'mkv', 'wmv', 'mov']
            ],
            [
                'id'                  => 'a_archive',
                'name'                => 'Archive',
                'extensions'          => ['zip', 'rar', '7z']
            ],
            [
                'id'                  => 'a_graphics',
                'name'                => 'Graphics',
                'extensions'          => ['ai', 'svg']
            ],
            [
                'id'                  => 'a_presentation',
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
                ->setParameter('id', $this->getIdGenerator()->toUuid($default['id']))
                ->setParameter('name', $default['name'])
                ->setParameter('system', 'system');
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }
}