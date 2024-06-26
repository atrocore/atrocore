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

class V1Dot10Dot36 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-06-26 17:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX idx_note_super_parent");
            $this->exec("DROP INDEX IDX_NOTE_PARENT_AND_SUPER_PARENT");
            $this->exec("ALTER TABLE note DROP super_parent_id");
            $this->exec("ALTER TABLE note DROP super_parent_type");
            $this->exec("CREATE INDEX IDX_NOTE_PARENT_AND_SUPER_PARENT ON note (parent_id, parent_type)");

            $this->exec("DROP INDEX idx_note_number");
            $this->exec("ALTER TABLE note DROP number");
        } else {
            $this->exec("DROP INDEX IDX_NOTE_SUPER_PARENT ON note");
            $this->exec("DROP INDEX IDX_NOTE_PARENT_AND_SUPER_PARENT ON note");
            $this->exec("ALTER TABLE note DROP super_parent_id, DROP super_parent_type");
            $this->exec("CREATE INDEX IDX_NOTE_PARENT_AND_SUPER_PARENT ON note (parent_id, parent_type)");

            $this->exec("DROP INDEX IDX_NOTE_NUMBER ON note");
            $this->exec("ALTER TABLE note DROP number");
        }

        $this->exec("ALTER TABLE note DROP is_internal");

        $this->updateComposer('atrocore/core', '^1.10.36');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
