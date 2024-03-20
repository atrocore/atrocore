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

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot9Dot9 extends Base
{
    public function up(): void
    {
        if($this->isPgSQL()){
            $this->execute('ALTER TABLE account ADD number VARCHAR(255) DEFAULT NULL;');
            $this->execute('CREATE UNIQUE INDEX UNIQ_7D3656A496901F54EB3B4E33 ON account (number, deleted);');
        }else{
            $this->execute('ALTER TABLE account ADD number VARCHAR(255) DEFAULT NULL;');
            $this->execute('CREATE UNIQUE INDEX UNIQ_7D3656A496901F54EB3B4E33 ON account (number, deleted)');
        }

    }

    public function down(): void
    {
        if($this->isPgSQL()){
            $this->execute('DROP INDEX uniq_7d3656a496901f54eb3b4e33;');
            $this->execute('ALTER TABLE account DROP number');
        }else{
            $this->execute('DROP INDEX UNIQ_7D3656A496901F54EB3B4E33 ON account;');
            $this->execute('ALTER TABLE account DROP number');
        }
    }

    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {

        }
    }
}
