<?php

declare(strict_types=1);

namespace Treo\Console;

/**
 * Class Cleanup
 *
 * @author r.ratsun@gmail.com
 */
class Cleanup extends AbstractConsole
{
    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Database and attachments clearing.';
    }

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        if ((new \Treo\Jobs\TreoCleanup($this->getContainer()))->run()) {
            self::show('Cleanup successfully finished', self::SUCCESS);
        } else {
            self::show('Something wrong. Cleanup failed. Check log for details', self::ERROR);
        }
    }
}
