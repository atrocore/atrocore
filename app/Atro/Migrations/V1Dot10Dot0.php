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

namespace Atro\Migrations;

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class V1Dot10Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-03-18');
    }

    public function up(): void
    {
        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('asset_type')
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $res = [];
        }

        foreach ($res as $v) {
            try {
                $this->getConnection()->createQueryBuilder()
                    ->delete('asset_type')
                    ->where('id = :id')
                    ->setParameter('id', $v['id'])
                    ->executeQuery();
            } catch (\Throwable $e) {
            }

            $this->getConnection()->createQueryBuilder()
                ->insert('file_type')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('assign_automatically', ':assignAutomatically')
                ->setValue('sort_order', ':sortOrder')
                ->setValue('created_by_id', ':createdById')
                ->setValue('modified_by_id', ':modifiedById')
                ->setParameter('id', $v['id'])
                ->setParameter('name', $v['name'])
                ->setParameter('assignAutomatically', !empty($v['assign_automatically']), ParameterType::BOOLEAN)
                ->setParameter('sortOrder', $v['sort_order'])
                ->setParameter('createdById', $v['created_by_id'])
                ->setParameter('modifiedById', $v['modified_by_id'])
                ->executeQuery();
        }

        try {
            $this->getConnection()->createQueryBuilder()
                ->update('validation_rule')
                ->set('file_type_id', 'asset_type_id')
                ->where('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        } catch (\Throwable $e) {
        }

        self::createDefaultFileTypes($this->getConnection());

        $this->getConfig()->remove('whitelistedExtensions');
        $this->getConfig()->save();

        $this->updateComposer('atrocore/core', '^1.10.0');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    public static function createDefaultFileTypes(Connection $conn): void
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
                'extensions'          => ['jpg', 'jpeg', 'gif', 'tiff', 'png', 'bmp']
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
            $qb = $conn->createQueryBuilder()
                ->insert('file_type')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('sort_order', ':sortOrder')
                ->setValue('assign_automatically', ':assignAutomatically')
                ->setValue('created_by_id', ':system')
                ->setValue('modified_by_id', ':system')
                ->setParameter('id', $default['id'])
                ->setParameter('name', $default['name'])
                ->setParameter('sortOrder', $k + 100)
                ->setParameter('assignAutomatically', $default['assignAutomatically'], ParameterType::BOOLEAN)
                ->setParameter('system', 'system');
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }

            $qb1 = $conn->createQueryBuilder()
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
