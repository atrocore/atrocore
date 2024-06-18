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

use Atro\Console\Cron;
use Atro\Core\Migration\Base;

class V1Dot7Dot28 extends Base
{
    public function up(): void
    {
        file_put_contents(Cron::DAEMON_KILLER, '1');
    }

    public function down(): void
    {
        file_put_contents(Cron::DAEMON_KILLER, '1');
    }
}
