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

use Atro\Core\ConsoleManager;

/**
 * ListCommand console
 */
class ListCommand extends AbstractConsole
{
    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Show all existing commands and their descriptions.';
    }

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        // get console config
        $config = $this->getConsoleConfig();

        // prepare data
        foreach ($config as $command => $class) {
            if (method_exists($class, 'getDescription') && empty($class::$isHidden)) {
                $data[$command] = [$command, $class::getDescription()];
            }
        }

        // sorting
        $sorted = array_keys($data);
        natsort($sorted);
        foreach ($sorted as $command) {
            $result[] = $data[$command];
        }

        // render
        self::show('Available commands:', self::INFO);
        echo self::arrayToTable($result);
    }

    /**
     * Get console config
     *
     * @return array
     */
    protected function getConsoleConfig(): array
    {
        return ConsoleManager::loadRoutes();
    }
}
