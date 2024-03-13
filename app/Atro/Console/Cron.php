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
use Atro\Core\QueueManager;
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
        if (empty($this->getConfig()->get('isInstalled'))) {
            exit(1);
        }

        // kill daemon killer
        if (file_exists(self::DAEMON_KILLER)) {
            unlink(self::DAEMON_KILLER);
        }

        // get active processes
        exec('ps ax | grep index.php', $processes);
        $processes = implode(' | ', $processes);

        /** @var string $php */
        $php = $this->getPhpBin();

        /** @var string $id */
        $id = $this->getConfig()->get('appId');

        // open daemon for composer
        if (empty(strpos($processes, "index.php daemon composer $id"))) {
            if ($this->isComposerDaemonBlocked()) {
                return;
            }

            exec("$php index.php daemon composer $id >/dev/null 2>&1 &");
        }

        // exit if system is updating now
        if (Application::isSystemUpdating()) {
            return;
        }

        // open daemon queue manager streams
        $queueManagerWorkersCount = $this->getConfig()->get('queueManagerWorkersCount', 4) + 1;
        $i = 0;
        while ($i <= $queueManagerWorkersCount) {
            if (empty(strpos($processes, "index.php daemon qm $i-$id"))) {
                exec("$php index.php daemon qm $i-$id >/dev/null 2>&1 &");
            }
            $i++;
        }

        // open daemon notification
        if (empty(strpos($processes, "index.php daemon notification $id"))) {
            exec("$php index.php daemon notification $id >/dev/null 2>&1 &");
        }

        // open daemon for pseudo transaction manager
        if (empty(strpos($processes, "index.php daemon pt $id"))) {
            exec("$php index.php daemon pt $id >/dev/null 2>&1 &");
        }

        // check auth tokens
        $this->authTokenControl();

        // find pending jobs without queue files and create them
        $this->createQueueFiles();

        // delete empty queue folders
        $this->deleteEmptyQueueFolders();

        // find and close queue item that doe not running
        $this->closeFailedQueueItems();

        // run cron jobs
        $this->runCronManager();
    }

    /**
     * Run cron manager
     */
    protected function runCronManager(): void
    {
        $auth = new \Espo\Core\Utils\Auth($this->getContainer());
        $auth->useNoAuth();

        $this->getContainer()->get('cronManager')->run();
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

        // @todo remove this after 01.06.2021
        if (strpos($log, 'Sending notification(s)') !== false) {
            unlink(Application::COMPOSER_LOG_FILE);
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

    private function createQueueFiles(): void
    {
        $repository = $this->getEntityManager()->getRepository('QueueItem');

        $items = $repository
            ->select(['id', 'sortOrder', 'priority'])
            ->where(['status' => 'Pending'])
            ->order('sortOrder')
            ->limit(0, 200)
            ->find();

        $created = false;
        foreach ($items as $item) {
            $filePath = $repository->getFilePath($item->get('sortOrder'), $item->get('priority'), $item->get('id'));
            if (!empty($filePath) && !file_exists($filePath)) {
                file_put_contents($filePath, $item->get('id'));
                $created = true;
            }
        }

        if ($created) {
            file_put_contents(QueueManager::FILE_PATH, '1');
        }
    }

    private function deleteEmptyQueueFolders(): void
    {
        $main = QueueManager::QUEUE_DIR_PATH;
        if (is_dir($main)) {
            foreach (scandir($main) as $item) {
                if (in_array($item, ['0', '000001', '88888888888888', '99999999999999', '.', '..'])) {
                    continue;
                }

                $subFolder = $main . '/' . $item;
                if (!is_dir($subFolder)) {
                    continue;
                }

                if (count(scandir($subFolder)) === 2) {
                    rmdir($subFolder);
                }
                break;
            }
        }
    }

    private function closeFailedQueueItems(): void
    {
        $repository = $this->getEntityManager()->getRepository('QueueItem');

        $items = $repository
            ->where(['status' => 'Running'])
            ->order('sortOrder')
            ->limit(0, 20)
            ->find();

        foreach ($items as $item) {
            $pid = $item->get('pid');
            if (!file_exists("/proc/$pid")) {
                $item->set('status', 'Failed');
                $item->set('message', "The item '{$item->get('id')}' was not completed in the previous run.");
                $repository->save($item);

                $GLOBALS['log']->error("QM failed: " . $item->get('message'));
            }
        }
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }
}
