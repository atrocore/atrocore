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

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;

class V1Dot10Dot33 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-06-11 12:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE ui_handler ADD disabled_options TEXT DEFAULT NULL; COMMENT ON COLUMN ui_handler.disabled_options IS '(DC2Type:jsonArray)'");

    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }
}
