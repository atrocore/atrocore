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

class V2Dot7Dot2 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-26 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE address RENAME COLUMN zip TO postal_code");
        } else {
            $this->exec("ALTER TABLE address CHANGE zip postal_code VARCHAR(255) DEFAULT NULL");
        }

        $this->renameLayoutFields();
    }

    private function renameLayoutFields(): void
    {
        $layoutIds = $this->getDbal()->createQueryBuilder()
            ->select('id')
            ->from('layout')
            ->where('entity = :entity')
            ->andWhere('deleted = :false')
            ->setParameter('entity', 'Address')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();

        if (empty($layoutIds)) {
            return;
        }

        $sectionIds = $this->getDbal()->createQueryBuilder()
            ->select('id')
            ->from('layout_section')
            ->where('layout_id IN (:layoutIds)')
            ->setParameter('layoutIds', $layoutIds, $this->getDbal()::PARAM_STR_ARRAY)
            ->fetchFirstColumn();

        if (!empty($sectionIds)) {
            try {
                $this->getDbal()->createQueryBuilder()
                    ->update('layout_row_item')
                    ->set('name', ':newName')
                    ->where('name = :oldName')
                    ->andWhere('section_id IN (:sectionIds)')
                    ->setParameter('newName', 'postalCode')
                    ->setParameter('oldName', 'zip')
                    ->setParameter('sectionIds', $sectionIds, $this->getDbal()::PARAM_STR_ARRAY)
                    ->executeStatement();
            } catch (\Throwable $e) {

            }
        }

        try {
            $this->getDbal()->createQueryBuilder()
                ->update('layout_list_item')
                ->set('name', ':newName')
                ->where('name = :oldName')
                ->andWhere('layout_id IN (:layoutIds)')
                ->setParameter('newName', 'postalCode')
                ->setParameter('oldName', 'zip')
                ->setParameter('layoutIds', $layoutIds, $this->getDbal()::PARAM_STR_ARRAY)
                ->executeStatement();
        } catch (\Throwable $e) {

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
