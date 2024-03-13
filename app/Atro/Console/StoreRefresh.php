<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Console;

/**
 * Class StoreRefresh
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
        return 'Refresh store.';
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

        self::show('Store refreshed successfully', self::SUCCESS);
    }

    /**
     * Refresh
     */
    protected function refresh(): void
    {
        // auth
        (new \Espo\Core\Utils\Auth($this->getContainer()))->useNoAuth();

        // refresh
        $this->getContainer()->get("serviceFactory")->create("TreoStore")->refresh();
    }
}
