<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot7Dot0 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE queue_item DROP position");
        $this->exec("ALTER TABLE pseudo_transaction_job CHANGE sort_order sort_order INT DEFAULT NULL");
        $this->exec("DROP INDEX UNIQ_9AEE3C0845AFA4EA ON pseudo_transaction_job");
        $this->exec("ALTER TABLE `user` ADD name VARCHAR(255) DEFAULT NULL");
        $this->exec("ALTER TABLE `user` DROP salutation_name");
        $this->exec("ALTER TABLE `user` ADD department VARCHAR(255) DEFAULT NULL");

        $this->updateComposer('atrocore/core', '^1.7.0');
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
