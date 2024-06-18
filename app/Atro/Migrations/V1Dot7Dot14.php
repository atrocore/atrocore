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

class V1Dot7Dot14 extends Base
{
    public function up(): void
    {
        $this->rebuild();
        $this->updateComposer('atrocore/core', '^1.7.14');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }
}
