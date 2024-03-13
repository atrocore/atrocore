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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot5Dot39 extends Base
{
    public function up(): void
    {
        $this->exec("DROP TABLE array_value");
    }

    public function down(): void
    {
        $this->getPDO()->exec("CREATE TABLE array_value (id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, value VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, attribute VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, entity_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, entity_type VARCHAR(100) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_ENTITY (entity_id, entity_type), INDEX IDX_ENTITY_TYPE_VALUE (entity_type, value), INDEX IDX_ENTITY_VALUE (entity_type, entity_id, value), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
