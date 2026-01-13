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

class V2Dot2Dot3 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-13 13:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE cluster ADD master_entity VARCHAR(255) DEFAULT NULL");

        $this->exec("DROP INDEX uniq_e5c5699477153098eb3b4e33");
        $this->exec("ALTER TABLE cluster DROP code");

        if ($this->isPgSQL()) {
            $this->exec("CREATE SEQUENCE cluster_number_seq INCREMENT BY 1 MINVALUE 1 START 1");
            $this->exec("ALTER TABLE cluster ADD number INT DEFAULT nextval('cluster_number_seq') NOT NULL");
        } else {
            $this->exec("ALTER TABLE cluster ADD number INT NOT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_E5C5699496901F54 ON cluster (number)");
            $this->exec("ALTER TABLE cluster CHANGE number number INT AUTO_INCREMENT NOT NULL");
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
