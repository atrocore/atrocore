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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot5Dot28 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("CREATE INDEX IDX_STATUS ON queue_item (status, deleted)");
        $this->getPDO()->exec("CREATE INDEX IDX_SORT_ORDER ON queue_item (sort_order, deleted)");

        $this->getPDO()->exec("DROP INDEX IDX_NUMBER ON notification");
        $this->getPDO()->exec("CREATE INDEX IDX_READ ON notification (`read`, deleted)");
        $this->getPDO()->exec("CREATE INDEX IDX_NUMBER ON notification (number, deleted)");
    }

    public function down(): void
    {
        $this->getPDO()->exec("DROP INDEX IDX_STATUS ON queue_item");
        $this->getPDO()->exec("DROP INDEX IDX_SORT_ORDER ON queue_item");

        $this->getPDO()->exec("DROP INDEX IDX_READ ON notification");
        $this->getPDO()->exec("DROP INDEX IDX_NUMBER ON notification");
        $this->getPDO()->exec("CREATE INDEX IDX_NUMBER ON notification (number)");
    }
}