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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V2Dot6Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-06 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE consolidation RENAME COLUMN execute_merge_as TO execute_consolidation_as");
            $this->exec("ALTER TABLE consolidation RENAME COLUMN confirm_automatically TO consolidate_automatically");
            $this->exec("ALTER TABLE cluster_item RENAME COLUMN confirmed_automatically TO consolidated_automatically");
        } else {
            $this->exec("ALTER TABLE consolidation CHANGE execute_merge_as execute_consolidation_as VARCHAR(255) DEFAULT 'system'");
            $this->exec("ALTER TABLE consolidation CHANGE confirm_automatically consolidate_automatically TINYINT(1) DEFAULT '0' NOT NULL");
            $this->exec("ALTER TABLE cluster_item CHANGE confirmed_automatically consolidated_automatically TINYINT(1) DEFAULT '0' NOT NULL");
        }

        $consolidations = $this->getDbal()->createQueryBuilder()
            ->select('id', 'consolidation_script')
            ->from('consolidation')
            ->where('consolidation_script IS NOT NULL')
            ->fetchAllAssociative();

        foreach ($consolidations as $consolidation) {
            $script = preg_replace('/\bcontributorRecords\b/', 'contributors', $consolidation['consolidation_script']);

            if ($script === $consolidation['consolidation_script']) {
                continue;
            }

            $this->getDbal()->createQueryBuilder()
                ->update('consolidation')
                ->set('consolidation_script', ':script')
                ->where('id = :id')
                ->setParameter('script', $script)
                ->setParameter('id', $consolidation['id'])
                ->executeStatement();
        }

        $renamedFields = [
            'Consolidation' => [
                'executeMergeAs'       => 'executeConsolidationAs',
                'confirmAutomatically' => 'consolidateAutomatically',
            ],
        ];

        foreach ($renamedFields as $entityName => $fields) {
            $layoutIds = $this->getDbal()->createQueryBuilder()
                ->select('id')
                ->from('layout')
                ->where('entity = :entity')
                ->andWhere('deleted = :false')
                ->setParameter('entity', $entityName)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchFirstColumn();

            if (empty($layoutIds)) {
                continue;
            }

            $sectionIds = $this->getDbal()->createQueryBuilder()
                ->select('id')
                ->from('layout_section')
                ->where('layout_id IN (:layoutIds)')
                ->setParameter('layoutIds', $layoutIds, $this->getDbal()::PARAM_STR_ARRAY)
                ->fetchFirstColumn();

            foreach ($fields as $oldName => $newName) {
                if (!empty($sectionIds)) {
                    $this->getDbal()->createQueryBuilder()
                        ->update('layout_row_item')
                        ->set('name', ':newName')
                        ->where('name = :oldName')
                        ->andWhere('section_id IN (:sectionIds)')
                        ->setParameter('newName', $newName)
                        ->setParameter('oldName', $oldName)
                        ->setParameter('sectionIds', $sectionIds, $this->getDbal()::PARAM_STR_ARRAY)
                        ->executeStatement();
                }

                $this->getDbal()->createQueryBuilder()
                    ->update('layout_list_item')
                    ->set('name', ':newName')
                    ->where('name = :oldName')
                    ->andWhere('layout_id IN (:layoutIds)')
                    ->setParameter('newName', $newName)
                    ->setParameter('oldName', $oldName)
                    ->setParameter('layoutIds', $layoutIds, $this->getDbal()::PARAM_STR_ARRAY)
                    ->executeStatement();
            }
        }
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
