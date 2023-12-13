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

use Atro\Console\Cron;
use Atro\Core\Migration\Base;

class V1Dot7Dot33 extends Base
{
    public function up(): void
    {
        $this->getConfig()->set('jobsMaxDays', 21);
        $this->getConfig()->set('scheduledJobLogsMaxDays', 21);
        $this->getConfig()->set('authLogsMaxDays', 21);
        $this->getConfig()->set('actionHistoryMaxDays', 21);
        $this->getConfig()->set('deletedAttachmentsMaxDays', 14);
        $this->getConfig()->set('deletedItemsMaxDays', 14);
        $this->getConfig()->set('cleanDbSchema', 21);
        $this->getConfig()->set('cleanEntityTeam', 21);
        $this->getConfig()->save();

        // remove DeleteForever job
        $this->exec("DELETE FROM scheduled_job WHERE id = 'DeleteForever'");
        $this->exec("DELETE FROM job WHERE scheduled_job_id = 'DeleteForever'");
    }

    public function down(): void
    {
        $this->exec("INSERT INTO scheduled_job (id, name, job, minimum_age, status, scheduling) VALUES ('DeleteForever','Delete data forever','DeleteForever',90,'Active','0 0 1 * *')");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
