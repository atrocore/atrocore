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

class V2Dot2Dot22 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-02-17 10:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE cluster_item ADD matched_score INT DEFAULT NULL;");
            $this->exec("ALTER TABLE cluster_item ADD confirmed_automatically BOOLEAN DEFAULT 'false' NOT NULL;");
            $this->exec("ALTER TABLE master_data_entity ADD confirm_automatically BOOLEAN DEFAULT 'false' NOT NULL;");
            $this->exec("ALTER TABLE master_data_entity ADD minimum_matching_score INT DEFAULT 100;");
            $this->exec("ALTER TABLE master_data_entity ADD execute_merge_as VARCHAR(255) DEFAULT 'system';");
        } else {
            $this->exec("ALTER TABLE cluster_item ADD matched_score INT DEFAULT NULL, ADD confirmed_automatically TINYINT(1) DEFAULT '0' NOT NULL;");
            $this->exec("ALTER TABLE master_data_entity ADD confirm_automatically TINYINT(1) DEFAULT '0' NOT NULL, ADD minimum_matching_score INT DEFAULT 100, ADD execute_merge_as VARCHAR(255) DEFAULT 'system';");
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
