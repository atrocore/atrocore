<?php

declare(strict_types=1);

namespace Treo\Console;

/**
 * Cron console
 *
 * @author r.ratsun@gmail.com
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
        $php = (new \Espo\Core\Utils\System())->getPhpBin();

        /** @var string $id */
        $id = $this->getConfig()->get('treoId');

        // open daemon for composer
        if (empty(strpos($processes, "index.php daemon composer $id"))) {
            exec("$php index.php daemon composer $id >/dev/null 2>&1 &");
        }

        // open daemon queue manager stream 0
        if (empty(strpos($processes, "index.php daemon qm 0-$id"))) {
            exec("$php index.php daemon qm 0-$id >/dev/null 2>&1 &");
        }

        // open daemon queue manager stream 1
        if (empty(strpos($processes, "index.php daemon qm 1-$id"))) {
            exec("$php index.php daemon qm 1-$id >/dev/null 2>&1 &");
        }

        // open daemon notification
        if (empty(strpos($processes, "index.php daemon notification $id"))) {
            exec("$php index.php daemon notification $id >/dev/null 2>&1 &");
        }

        // run cron jobs
        $this->runCronManager();
    }

    /**
     * Run cron manager
     */
    protected function runCronManager(): void
    {
        $auth = new \Treo\Core\Utils\Auth($this->getContainer());
        $auth->useNoAuth();

        $this->getContainer()->get('cronManager')->run();
    }
}
