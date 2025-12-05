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

declare(strict_types=1);

namespace Atro\Console;

use Atro\Core\Application;
use Atro\Core\JobManager;
use Espo\ORM\EntityManager;

/**
 * Cron console
 */
class Cron extends AbstractConsole
{
    const DAEMON_KILLER = 'data/process-kill.txt';

    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Run CRON.';
    }

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled')) || file_exists(Application::COMPOSER_LOG_FILE)) {
            exit(1);
        }

        // kill daemon killer
        if (file_exists(self::DAEMON_KILLER)) {
            unlink(self::DAEMON_KILLER);
        }

        // get active processes
        exec('ps ax | grep console.php', $processes);
        $processes = implode(' | ', $processes);

        /** @var string $php */
        $php = $this->getPhpBin();

        /** @var string $id */
        $id = $this->getConfig()->get('appId');

        // open daemon for composer
        if (empty(strpos($processes, "console.php daemon composer $id"))) {
            if ($this->isComposerDaemonBlocked()) {
                return;
            }

            exec("$php console.php daemon composer $id >/dev/null 2>&1 &");
        }

        // exit if system is updating now
        if (Application::isSystemUpdating()) {
            return;
        }

        // open daemon for pseudo transaction manager
        if (empty(strpos($processes, "console.php daemon pt $id"))) {
            exec("$php console.php daemon pt $id >/dev/null 2>&1 &");
        }

        // open daemon for job manager
        if (empty(strpos($processes, "console.php daemon job-manager $id"))) {
            exec("$php console.php daemon job-manager $id >/dev/null 2>&1 &");
        }

        $auth = new \Espo\Core\Utils\Auth($this->getContainer());
        $auth->useNoAuth();

        // check auth tokens
        $this->authTokenControl();

        // find pending job to create queue file
        $this->createQueueFile();

        // find and close jobs that has not finished
        $this->closeFailedJobs();

        // clear realtime manager data
        $this->getContainer()->get('realtimeManager')->clear();

        // run cron jobs
        $this->runCronManager();
    }

    /**
     * Run cron manager
     */
    protected function runCronManager(): void
    {
        $scheduledJobs = $this->getEntityManager()->getRepository('ScheduledJob')
            ->where(['isActive' => true])
            ->find();

        foreach ($scheduledJobs as $scheduledJob) {
            try {
                $cronExpression = \Cron\CronExpression::factory($scheduledJob->get('scheduling'));
            } catch (\Exception $e) {
                $GLOBALS['log']->error("ScheduledJob '{$scheduledJob->id}' Failed: {$e->getMessage()}.");
                continue;
            }

            try {
                $nextDate = $cronExpression->getNextRunDate()->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $GLOBALS['log']->error("Unsupported CRON expression '{$scheduledJob->get('scheduling')}'");
                continue;
            }

            $exists = $this->getEntityManager()->getRepository('Job')
                ->where([
                    'status'         => 'Pending',
                    'scheduledJobId' => $scheduledJob->get('id'),
                    'executeTime'    => $nextDate
                ])
                ->findOne();

            if (empty($exists)) {
                $jobEntity = $this->getEntityManager()->getEntity('Job');
                $jobEntity->set([
                    'name'           => $scheduledJob->get('name'),
                    'type'           => $scheduledJob->get('type'),
                    'scheduledJobId' => $scheduledJob->get('id'),
                    'executeTime'    => $nextDate
                ]);
                $this->getEntityManager()->saveEntity($jobEntity);
            }
        }
    }

    /**
     * @return bool
     */
    private function isComposerDaemonBlocked(): bool
    {
        if (!file_exists(Application::COMPOSER_LOG_FILE)) {
            return false;
        }

        $log = file_get_contents(Application::COMPOSER_LOG_FILE);

        if (strpos($log, 'Creating restore point') === false) {
            return false;
        }

        return true;
    }

    private function authTokenControl(): void
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        $tokenList = $em
            ->getRepository('AuthToken')
            ->select(['id', 'lifetime', 'idleTime', 'createdAt', 'lastAccess'])
            ->where(['isActive' => true])
            ->find();

        foreach ($tokenList as $token) {
            $authTokenLifetime = $token->get('lifetime') !== null ? $token->get('lifetime') : $this->getConfig()->get('authTokenLifetime');
            if ($authTokenLifetime && new \DateTime($token->get('createdAt')) < (new \DateTime())->modify('-' . $authTokenLifetime . ' hours')) {
                $token->set('isActive', false);
                $em->saveEntity($token);
                continue 1;
            }

            $authTokenMaxIdleTime = $token->get('idleTime') !== null ? $token->get('idleTime') : $this->getConfig()->get('authTokenMaxIdleTime');
            if ($authTokenMaxIdleTime && new \DateTime($token->get('lastAccess')) < (new \DateTime())->modify('-' . $authTokenMaxIdleTime . ' hours')) {
                $token->set('isActive', false);
                $em->saveEntity($token);
            }
        }
    }

    private function createQueueFile(): void
    {
        if (file_exists(JobManager::QUEUE_FILE)) {
            return;
        }

        $job = $this->getEntityManager()->getRepository('Job')
            ->where([
                'status'        => 'Pending',
                'type!='        => null,
                'executeTime<=' => (new \DateTime())->format('Y-m-d H:i:s')
            ])
            ->findOne();

        if (!empty($job)) {
            file_put_contents(JobManager::QUEUE_FILE, '1');
        }
    }

    private function closeFailedJobs(): void
    {
        if (file_exists(JobManager::QUEUE_FILE)) {
            return;
        }

        $jobs = $this->getEntityManager()->getRepository('Job')
            ->where([
                'status' => 'Running',
                'pid!='  => null
            ])
            ->limit(0, 10)
            ->find();

        foreach ($jobs as $job) {
            $pid = $job->get('pid');
            if (!file_exists("/proc/$pid")) {
                $job->set('status', 'Failed');
                $job->set('message', "The Job '{$job->get('id')}' was not completed in the previous run.");
                $this->getEntityManager()->saveEntity($job);

                $GLOBALS['log']->error("Job failed: " . $job->get('message'));
            }
        }
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }
}
