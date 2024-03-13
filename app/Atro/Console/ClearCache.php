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
 * ClearCache console
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
