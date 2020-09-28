<?php

declare(strict_types=1);

namespace Treo\Console;

/**
 * Class QueueManager
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class QueueManager extends AbstractConsole
{
    /**
     * @inheritdoc
     */
    public static function getDescription(): string
    {
        return 'Run Queue Manager job.';
    }

    /**
     * @inheritdoc
     */
    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled'))) {
            exit(1);
        }

        // run
        $this->getContainer()->get('queueManager')->run((int)$data['stream']);

        self::show('Queue Manager runned successfully', self::SUCCESS, true);
    }
}
