<?php

declare(strict_types=1);

namespace Treo\Console;

/**
 * Rebuild console
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Rebuild extends AbstractConsole
{
    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Run database rebuild.';
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
            ->rebuild();

        if (!empty($result)) {
            self::show('Rebuild successfully finished', self::SUCCESS);
        } else {
            self::show('Something wrong. Rebuild failed. Check log for details', self::ERROR);
        }
    }
}
