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

class V1Dot8Dot38 extends Base
{
    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('unit')
            ->set('is_default', ':false')
            ->where('is_default is null')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeStatement();

        if ($this->isPgSQL()) {
            $this->execute("ALTER TABLE unit RENAME COLUMN is_default TO is_main");
        } else {
            $this->execute("ALTER TABLE unit CHANGE is_default is_main TINYINT(1)");
        }
    }

    public function down(): void
    {
        if ($this->isPgSQL()) {
            $this->execute("ALTER TABLE unit RENAME COLUMN is_main TO is_default");
        } else {
            $this->execute("ALTER TABLE unit CHANGE is_main is_default TINYINT(1)");
        }
    }

    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
