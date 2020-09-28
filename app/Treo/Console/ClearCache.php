<?php

declare(strict_types=1);

namespace Treo\Console;

/**
 * ClearCache console
 *
 * @author r.ratsun@zinitsolutions.com
 */
class ClearCache extends AbstractConsole
{
    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Cache clearing.';
    }

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        $result = $this
            ->getContainer()
            ->get('dataManager')
            ->clearCache();

        if (!empty($result)) {
            self::show('Cache successfully cleared', self::SUCCESS);
        } else {
            self::show('Cache clearing failed', self::ERROR);
        }
    }
}
