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

use Atro\Console\Cron;
use Atro\Core\Migration\Base;

class V1Dot7Dot37 extends Base
{
    public function up(): void
    {
        $this->getConfig()->set('jobsMaxDays', 21);
        $this->getConfig()->set('scheduledJobLogsMaxDays', 21);
        $this->getConfig()->set('authLogsMaxDays', 21);
        $this->getConfig()->set('actionHistoryMaxDays', 21);
        $this->getConfig()->set('deletedAttachmentsMaxDays', 14);
        $this->getConfig()->set('deletedItemsMaxDays', 14);
        $this->getConfig()->set('cleanDbSchema', true);
        $this->getConfig()->set('cleanEntityTeam', true);
        $this->getConfig()->save();

        // remove DeleteForever job
        $connection = $this->getConnection();
        $connection
            ->createQueryBuilder()
            ->delete('job')
            ->where('scheduled_job_id = :id')
            ->setParameter('id', 'DeleteForever')
            ->executeStatement();
        $connection
            ->createQueryBuilder()
            ->delete('scheduled_job')
            ->where('id = :id')
            ->setParameter('id', 'DeleteForever')
            ->executeStatement();
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }
}
