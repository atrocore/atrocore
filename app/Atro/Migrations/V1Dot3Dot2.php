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

/**
 * Migration class for version 1.3.2
 */
class V1Dot3Dot2 extends Base
{
    public function up(): void
    {
        try {
            copy('vendor/atrocore/core/copy/robots.txt', 'robots.txt');
        } catch (\Throwable $e) {
            // ignore all
        }
    }

    public function down(): void
    {
    }
}
