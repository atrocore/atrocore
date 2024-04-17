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

declare(strict_types = 1);

namespace Atro\Migrations;

class V1Dot10Dot3 extends V1Dot9Dot19
{
    public function up(): void
    {
        parent::up();

        $this->updateComposer('atrocore/core', '^1.10.3');
    }
}
