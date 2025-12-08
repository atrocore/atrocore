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
use Doctrine\DBAL\ParameterType;

class V2Dot1Dot35 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-12-09 12:00:00');
    }

    public function up(): void
    {
//        $this->exec("truncate matched_record");
//        $this->exec("truncate matching");
//        $this->exec("truncate matching_rule");

        // ALTER TABLE matching RENAME COLUMN source_entity TO entity

        // DROP INDEX IDX_MATCHED_RECORD_STAGING_ENTITY;
        //ALTER TABLE matched_record ADD staging_entity VARCHAR(255) DEFAULT NULL;
        //ALTER TABLE matched_record ADD staging_entity_id VARCHAR(255) DEFAULT NULL;
        //ALTER TABLE matched_record DROP source_entity;
        //ALTER TABLE matched_record DROP source_entity_id;
        //CREATE INDEX IDX_MATCHED_RECORD_STAGING_ENTITY ON matched_record (type, staging_entity, staging_entity_id)
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
