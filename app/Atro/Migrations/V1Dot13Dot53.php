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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot13Dot53 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-04-18 17:00:00');
    }
    public function up(): void
    {
        if($this->isPgSQL()) {
            $this->exec("ALTER TABLE saved_search ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
            $this->exec("ALTER TABLE saved_search ADD modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
            $this->exec("ALTER TABLE saved_search ADD created_by_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE saved_search ADD modified_by_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_SAVED_SEARCH_CREATED_BY_ID ON saved_search (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_SAVED_SEARCH_MODIFIED_BY_ID ON saved_search (modified_by_id, deleted)");
        }else{
            $this->exec("ALTER TABLE saved_search ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL, ADD created_by_id VARCHAR(36) DEFAULT NULL, ADD modified_by_id VARCHAR(36) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_SAVED_SEARCH_CREATED_BY_ID ON saved_search (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_SAVED_SEARCH_MODIFIED_BY_ID ON saved_search (modified_by_id, deleted)");
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
