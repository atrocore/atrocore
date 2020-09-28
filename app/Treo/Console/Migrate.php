<?php

declare(strict_types=1);

namespace Treo\Console;

/**
 * Migrate console
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Migrate extends AbstractConsole
{
    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Run migration.';
    }

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        $this
            ->getContainer()
            ->get('migration')
            ->run($data['module'], $data['from'], $data['to']);
    }
}
