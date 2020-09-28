<?php

declare(strict_types=1);

namespace Treo\Console;

/**
 * Class StoreRefresh
 *
 * @author r.ratsun@gmail.com
 */
class StoreRefresh extends AbstractConsole
{
    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Refresh TreoStore.';
    }

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        // refresh
        $this->refresh();

        self::show('TreoStore refreshed successfully', self::SUCCESS);
    }

    /**
     * Refresh
     */
    protected function refresh(): void
    {
        // auth
        (new \Treo\Core\Utils\Auth($this->getContainer()))->useNoAuth();

        // refresh
        $this->getContainer()->get("serviceFactory")->create("TreoStore")->refresh();
    }
}
