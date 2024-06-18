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

class V1Dot6Dot51 extends Base
{
    public function up(): void
    {
        $this->exec("CREATE INDEX IDX_PARENT_TYPE ON note (parent_type)");

        $this->exec("DROP INDEX UNIQ_BA4B6DE845AFA4EA ON queue_item");

        $this->exec("ALTER TABLE queue_item CHANGE sort_order sort_order DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE queue_item ADD position INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->exec("UPDATE queue_item SET sort_order=position WHERE deleted=0");
        $this->exec("ALTER TABLE queue_item ADD parent_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");

        $this->exec("CREATE INDEX IDX_PARENT_ID ON queue_item (parent_id)");
        $this->exec("CREATE INDEX IDX_PARENT_ID_DELETED ON queue_item (parent_id, deleted)");
        $this->exec("CREATE UNIQUE INDEX UNIQ_BA4B6DE8462CE4F5 ON queue_item (position)");

        $this->exec("DROP INDEX position ON queue_item");

        $this->updateComposer('atrocore/core', '^1.6.51');
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
