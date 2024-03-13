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
 * Class KillDaemons
 */
class KillDaemons extends AbstractConsole
{
    /**
     * @inheritdoc
     */
    public static function getDescription(): string
    {
        return 'Kill all daemons.';
    }

    /**
     * @inheritdoc
     */
    public function run(array $data): void
    {
        file_put_contents('data/process-kill.txt', '1');
        self::show("All daemons killed successfully", self::SUCCESS, true);
    }
}
