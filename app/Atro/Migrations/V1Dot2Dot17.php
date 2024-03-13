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
 * Migration for version 1.2.17
 */
class V1Dot2Dot17 extends V1Dot2Dot0
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->execute("ALTER TABLE `auth_token` ADD lifetime INT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD idle_time INT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("DELETE FROM scheduled_job WHERE job='AuthTokenControl'");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->execute("ALTER TABLE `auth_token` DROP lifetime, DROP idle_time");
    }
}
