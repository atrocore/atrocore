<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.md, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Console;

/**
 * Class Cleanup
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
        if ((new \Espo\Jobs\TreoCleanup($this->getContainer()))->run()) {
            self::show('Cleanup successfully finished', self::SUCCESS);
        } else {
            self::show('Something wrong. Cleanup failed. Check log for details', self::ERROR);
        }
    }
}
