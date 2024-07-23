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

class V1Dot10Dot45 extends V1Dot10Dot37
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-23 11:00:00');
    }

    public function up(): void
    {
        parent::up();
    }

    public function down(): void
    {
        parent::down();
    }
}
