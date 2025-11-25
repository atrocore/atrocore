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
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Espo\ORM\EntityManager;

class V2Dot1Dot28 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-11-20 09:00:00');
    }

    public function up(): void
    {
       if($this->isPgSQL()) {
            $this->exec("ALTER TABLE selection ADD type VARCHAR(255) DEFAULT NULL");
       }else{
           $this->exec("ALTER TABLE selection ADD type VARCHAR(255) DEFAULT NULL");
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
