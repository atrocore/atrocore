<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot6Dot36 extends Base
{
    public function up(): void
    {
        copy('vendor/atrocore/core/copy/.htaccess', '.htaccess');
        copy('vendor/atrocore/core/copy/index.php', 'index.php');
    }

    public function down(): void
    {
        copy('vendor/atrocore/core/copy/.htaccess', '.htaccess');
        copy('vendor/atrocore/core/copy/index.php', 'index.php');
    }
}
