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
use Atro\ORM\DB\RDB\Mapper;

class V1Dot13Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-01-20 17:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()){
            $this->exec("CREATE TABLE user_entity_layout (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', entity VARCHAR(255) DEFAULT NULL, view_type VARCHAR(255) DEFAULT NULL, related_entity VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, layout_profile_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX IDX_USER_ENTITY_LAYOUT_UNIQUE ON user_entity_layout (user_id, entity, view_type, related_entity, layout_profile_id, deleted);");
            $this->exec("CREATE INDEX IDX_USER_ENTITY_LAYOUT_USER_ID ON user_entity_layout (user_id, deleted);");
            $this->exec("CREATE INDEX IDX_USER_ENTITY_LAYOUT_MODIFIED_BY_ID ON user_entity_layout (modified_by_id, deleted)");
        }

    }


    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
