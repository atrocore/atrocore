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

class V1Dot6Dot22 extends Base
{
    public function up(): void
    {
       $this->rebuildByCronJob();
    }

    public function down(): void
    {
        $this->rebuildByCronJob();
    }
}
