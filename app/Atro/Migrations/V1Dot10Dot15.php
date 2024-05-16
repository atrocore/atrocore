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

use Atro\Core\Migration\Base;

class V1Dot10Dot15 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-05-16 17:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE ui_handler ADD trigger_action VARCHAR(255) DEFAULT NULL");
            $this->exec("ALTER TABLE ui_handler ADD trigger_fields TEXT DEFAULT NULL");
            $this->exec("COMMENT ON COLUMN ui_handler.trigger_fields IS '(DC2Type:jsonArray)'");
        } else {
            $this->exec("ALTER TABLE ui_handler ADD trigger_action VARCHAR(255) DEFAULT NULL");
            $this->exec("ALTER TABLE ui_handler ADD trigger_fields LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)'");
        }

        $this->getConnection()->createQueryBuilder()
            ->update('ui_handler')
            ->set('trigger_action', ':val')
            ->setParameter('val', 'ui_on_change')
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->update('extensible_enum_option')
            ->set('name', ':name')
            ->where('id = :id')
            ->setParameter('name', 'Make Visible')
            ->setParameter('id', 'ui_visible')
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->update('extensible_enum_option')
            ->set('name', ':name')
            ->where('id = :id')
            ->setParameter('name', 'Make Required')
            ->setParameter('id', 'ui_required')
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->update('extensible_enum_option')
            ->set('name', ':name')
            ->where('id = :id')
            ->setParameter('name', 'Make Read-only')
            ->setParameter('id', 'ui_read_only')
            ->executeQuery();
    }

    public function down(): void
    {
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
