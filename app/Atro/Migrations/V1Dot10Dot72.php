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
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot10Dot72 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-09-19 09:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE preferences ALTER id TYPE VARCHAR(36)");
        $this->exec("ALTER TABLE user_followed_record ALTER entity_id TYPE VARCHAR(36)");
        $this->exec("ALTER TABLE user_followed_record ALTER user_id TYPE VARCHAR(36)");
        $this->exec("ALTER TABLE entity_team ALTER entity_id TYPE VARCHAR(36)");

        $this->rebuild();
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
