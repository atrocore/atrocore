<?php
/*
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
use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot12Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-11 10:00:00');
    }

    public function up(): void
    {
        file_put_contents(Cron::DAEMON_KILLER, '1');

        $this->getConfig()->set('maxConcurrentWorkers', $this->getConfig()->get('queueManagerWorkersCount', 6));
        $this->getConfig()->save();

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE job ADD priority DOUBLE PRECISION DEFAULT '100'");
            $this->exec("ALTER TABLE job ADD type VARCHAR(255) DEFAULT NULL");
            $this->exec("ALTER TABLE job ADD payload TEXT DEFAULT NULL;COMMENT ON COLUMN job.payload IS '(DC2Type:jsonObject)'");
            $this->exec("ALTER TABLE job ADD owner_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE job ADD assigned_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_JOB_OWNER_USER_ID ON job (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_JOB_ASSIGNED_USER_ID ON job (assigned_user_id, deleted)");
            $this->exec("ALTER TABLE scheduled_job ADD type VARCHAR(255) DEFAULT NULL");
            $this->exec("ALTER TABLE job ADD message TEXT DEFAULT NULL");
            $this->exec("ALTER TABLE job ADD started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
            $this->exec("ALTER TABLE job ADD ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
        } else {
            $this->exec("DROP INDEX IDX_JOB_QUEUE_ITEM_ID ON job");
            $this->exec("ALTER TABLE job ADD message LONGTEXT DEFAULT NULL, ADD started_at DATETIME DEFAULT NULL, ADD ended_at DATETIME DEFAULT NULL, ADD priority DOUBLE PRECISION DEFAULT '100', ADD type VARCHAR(255) DEFAULT NULL, ADD assigned_user_id VARCHAR(36) DEFAULT NULL, CHANGE data payload LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', CHANGE queue_item_id owner_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_JOB_OWNER_USER_ID ON job (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_JOB_ASSIGNED_USER_ID ON job (assigned_user_id, deleted)");
            $this->exec("ALTER TABLE scheduled_job CHANGE scheduling scheduling VARCHAR(255) DEFAULT '0 2 * * *', CHANGE is_internal is_active TINYINT(1) DEFAULT '0' NOT NULL");
            $this->exec("ALTER TABLE scheduled_job ADD type VARCHAR(255) DEFAULT NULL");
        }

        $this->getConnection()->createQueryBuilder()
            ->update('scheduled_job')
            ->set('type', 'job')
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->update('scheduled_job')
            ->set('is_active', ':true')
            ->where('deleted=:false')
            ->andWhere('status=:active')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('active', 'Active')
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->delete($this->getConnection()->quoteIdentifier('job'))
            ->where('status=:pending OR status=:running OR deleted=:true')
            ->setParameter('pending', 'Pending')
            ->setParameter('running', 'Running')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeQuery();

        $this->updateComposer('atrocore/core', '^1.12.0');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
