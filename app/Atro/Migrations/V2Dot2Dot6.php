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

class V2Dot2Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-16 14:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE SEQUENCE matching_rule_number_seq INCREMENT BY 1 MINVALUE 1 START 1");
            $this->exec("DROP INDEX uniq_bacff97b77153098eb3b4e33");
            $this->exec("ALTER TABLE matching_rule ADD number INT DEFAULT nextval('matching_rule_number_seq') NOT NULL");
            $this->exec("ALTER TABLE matching_rule DROP code");
        } else {
            $this->exec("DROP INDEX UNIQ_BACFF97B77153098EB3B4E33 ON matching_rule");
            $this->exec("ALTER TABLE matching_rule ADD number INT NOT NULL, DROP code");
            $this->exec("CREATE UNIQUE INDEX UNIQ_BACFF97B96901F54 ON matching_rule (number)");
            $this->exec("ALTER TABLE matching_rule CHANGE number number INT AUTO_INCREMENT NOT NULL");
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
