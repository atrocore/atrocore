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

namespace Atro\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class RefreshStoragesItems extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Refresh storages items.';
    }

    public function run(array $data): void
    {
        $this->createFoldersItems();
        self::show("Folders items has been refreshed successfully.", self::SUCCESS);

        $this->createFilesItems();
        self::show("Files items has been refreshed successfully.", self::SUCCESS);
    }

    public function createFoldersItems(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->delete('file_folder_linker')
            ->where('folder_id IS NOT NULL')
            ->executeQuery();

        $records = $this->getConnection()->createQueryBuilder()
            ->select('f.*, h.parent_id')
            ->from('folder', 'f')
            ->leftJoin('f', 'folder_hierarchy', 'h', 'f.id=h.entity_id')
            ->where('f.deleted=:false')
            ->andWhere('f.deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $this->getConnection()->createQueryBuilder()
                ->insert('file_folder_linker')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('parent_id', ':parentId')
                ->setValue('folder_id', ':folderId')
                ->setParameter('id', Util::generateId())
                ->setParameter('name', (string)$record['name'])
                ->setParameter('parentId', (string)$record['parent_id'])
                ->setParameter('folderId', (string)$record['id'])
                ->executeQuery();
        }
    }

    public function createFilesItems(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->delete('file_folder_linker')
            ->where('file_id IS NOT NULL')
            ->executeQuery();

        $records = $this->getConnection()->createQueryBuilder()
            ->select('f.*, s.path as storage_path')
            ->from('file', 'f')
            ->innerJoin('f', 'storage', 's', 'f.storage_id=s.id')
            ->where('f.deleted=:false')
            ->andWhere('s.deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $this->getConnection()->createQueryBuilder()
                ->insert('file_folder_linker')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('parent_id', ':parentId')
                ->setValue('file_id', ':fileId')
                ->setParameter('id', Util::generateId())
                ->setParameter('name', (string)$record['name'])
                ->setParameter('parentId', (string)$record['folder_id'])
                ->setParameter('fileId', (string)$record['id'])
                ->executeQuery();
        }
    }

    protected function getConnection(): Connection
    {
        return $this->getContainer()->get('connection');
    }
}
