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

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;

class V1Dot11Dot56 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-04 10:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()) {
            $this->exec("ALTER TABLE file ADD width DOUBLE PRECISION DEFAULT NULL;");
            $this->exec("ALTER TABLE file ADD height DOUBLE PRECISION DEFAULT NULL;");
            $this->exec("ALTER TABLE file ADD color_space VARCHAR(255) DEFAULT NULL;");
            $this->exec("ALTER TABLE file ADD width_unit_id VARCHAR(36) DEFAULT NULL;");
            $this->exec("ALTER TABLE file ADD height_unit_id VARCHAR(36) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_FILE_WIDTH_UNIT_ID ON file (width_unit_id, deleted);");
            $this->exec("CREATE INDEX IDX_FILE_HEIGHT_UNIT_ID ON file (height_unit_id, deleted)");
        }else{
            $this->exec("LTER TABLE file ADD width DOUBLE PRECISION DEFAULT NULL, ADD height DOUBLE PRECISION DEFAULT NULL, AD
D color_space VARCHAR(255) DEFAULT NULL, ADD width_unit_id VARCHAR(36) DEFAULT NULL, ADD height_unit_id VARCHAR(36) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_FILE_WIDTH_UNIT_ID ON file (width_unit_id, deleted);");
            $this->exec("CREATE INDEX IDX_FILE_HEIGHT_UNIT_ID ON file (height_unit_id, deleted);");
        }
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
