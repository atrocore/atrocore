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

namespace Atro\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Espo\ORM\EntityManager;

/**
 * Creates the default local storage and the default file types.
 */
class CreateFileStorage implements AfterInstallInterface
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public static function getSortOrder(): int
    {
        return 30;
    }

    public function run(): void
    {
        $this->create('StorageTypeLocal', [
            'name'        => 'Base',
            'folderId'    => '',
            'isActive'    => true,
            'path'        => 'upload/files',
            'syncFolders' => false,
        ]);

        $fileTypes = [
            'Document'     => ['docx', 'doc', 'odt', 'rtf', 'tex', 'txt', 'pdf'],
            'Spreadsheet'  => ['xlsx', 'xls', 'ods', 'csv', 'tsv'],
            'Image'        => ['jpg', 'jpeg', 'gif', 'tiff', 'png', 'bmp', 'webp', 'avif'],
            'Icon'         => ['ico', 'png', 'svg'],
            'Audio'        => ['mp3', 'wav', 'aac', 'flac', 'ogg'],
            'Video'        => ['mp4', 'avi', 'mkv', 'wmv', 'mov'],
            'Archive'      => ['zip', 'rar', '7z'],
            'Graphics'     => ['ai', 'svg'],
            'Presentation' => ['pptx', 'ppt', 'ppsx', 'odp', 'key'],
        ];

        foreach ($fileTypes as $name => $extensions) {
            $this->create('FileType', [
                'name'       => $name,
                'extensions' => $extensions,
            ]);
        }
    }

    private function create(string $entityName, array $data): void
    {
        $entity = $this->entityManager->getRepository($entityName)->get();
        $entity->set($data);

        $this->entityManager->saveEntity($entity);
    }
}
