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

namespace Atro\Migrations;

use Atro\Core\Application;
use Atro\Core\Migration\Base;
use Atro\Repositories\File;
use Atro\Services\Avatar;

class V2Dot3Dot23 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-18 12:00:00');
    }

    public function up(): void
    {
        // migrate files based avatars
        $this->migrateFileBasedAvatars();

        // drop avatar column
        $this->dropAvatarColumn();
    }

    protected function migrateFileBasedAvatars(): void
    {
        $rows = $this->getDbal()->createQueryBuilder()
            ->select('id', 'avatar_id')
            ->from('user')
            ->where('avatar_id IS NOT NULL')
            ->andWhere("avatar_id != ''")
            ->andWhere('deleted = :false')
            ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        if (empty($rows)) {
            return;
        }

        $container = (new Application())->getContainer();
        /* @var \Espo\Core\ORM\EntityManager $entityManager */
        $entityManager = $container->get('entityManager');
        /* @var Avatar $service */
        $service = $container->get('serviceFactory')->create('Avatar');

        /** @var File $fileRepository */
        $fileRepository = $entityManager->getRepository('File');

        foreach ($rows as $row) {
            $file = $entityManager->getEntity('File', $row['avatar_id']);
            if (empty($file)) {
                continue;
            }

            $mimeType = $file->get('mimeType');
            if (!str_starts_with($mimeType, 'image/')) {
                continue;
            }

            try {
                $contents = $fileRepository->getContents($file);
            } catch (\Throwable $e) {
                continue;
            }

            if (empty($contents)) {
                continue;
            }

            $dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode($contents);

            try {
                $service->upload($row['id'], $dataUrl, $file->get('name'), $file->get('fileSize'));
            } catch (\Throwable) {
            }
        }
    }

    protected function dropAvatarColumn(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $table = $toSchema->getTable('user');
        if ($table->hasColumn('avatar_id')) {
            $this->dropColumn($toSchema, 'user', 'avatar_id');
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable) {
        }
    }
}
