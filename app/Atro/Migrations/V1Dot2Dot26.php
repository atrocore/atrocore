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

/**
 * Migration for version 1.2.26
 */
class V1Dot2Dot26 extends V1Dot2Dot17
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        copy('vendor/atrocore/core/copy/.htaccess', '.htaccess');
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
    }
}
