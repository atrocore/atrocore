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

use Espo\Jobs\CheckUpdates as Job;

class CheckUpdates extends AbstractConsole
{
    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Check is system need to be updated.';
    }

    /**
     * @inheritDoc
     */
    public function run(array $data): void
    {
        (new Job($this->getContainer()))->run();

        if (Job::isUpdatesAvailable()) {
            self::show('Updates available.', self::SUCCESS);
        } else {
            self::show('There is nothing to update.', self::SUCCESS);
        }
    }
}