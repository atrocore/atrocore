<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot4Dot91 extends Base
{
    public function up(): void
    {
        $this->execute("DROP INDEX IDX_SOURCE_ID ON attachment");
        $this->execute("ALTER TABLE attachment DROP source_id");

        $this->execute("DROP INDEX IDX_PARENT ON attachment");
        $this->execute("ALTER TABLE attachment DROP parent_id, DROP parent_type");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE attachment ADD source_id VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->execute("CREATE INDEX IDX_SOURCE_ID ON attachment (source_id)");

        $this->execute("ALTER TABLE attachment ADD parent_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD parent_type VARCHAR(100) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->execute("CREATE INDEX IDX_PARENT ON attachment (parent_type, parent_id)");
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
