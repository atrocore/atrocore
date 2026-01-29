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

use Atro\Core\Migration\Base;

class V2Dot2Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-29 10:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX IDX_SELECTION_ITEM_UNIQUE");
            $this->exec("DROP INDEX IDX_SELECTION_ITEM_ENTITY_NAME");
            $this->exec("ALTER TABLE selection_item RENAME COLUMN entity_type TO entity_name");
            $this->exec("ALTER TABLE selection_item RENAME COLUMN entity_type TO entity_name");
            $this->exec("CREATE INDEX IDX_SELECTION_ITEM_ENTITY_NAME ON selection_item (entity_name, deleted)");
            $this->exec("CREATE UNIQUE INDEX IDX_SELECTION_ITEM_UNIQUE ON selection_item (deleted, entity_name, entity_id, selection_id)");

        } else {
            $this->exec("DROP INDEX IDX_SELECTION_ITEM_ENTITY_NAME ON selection_item;");
            $this->exec("DROP INDEX IDX_SELECTION_ITEM_UNIQUE ON selection_item;");
            $this->exec("ALTER TABLE selection_item CHANGE entity_type entity_name VARCHAR(255) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_SELECTION_ITEM_ENTITY_NAME ON selection_item (entity_name, deleted);");
            $this->exec("CREATE UNIQUE INDEX IDX_SELECTION_ITEM_UNIQUE ON selection_item (deleted, entity_name, entity_id, selection_id)");
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
